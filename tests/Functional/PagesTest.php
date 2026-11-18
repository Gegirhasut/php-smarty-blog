<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Core\Response;
use App\Tests\DatabaseTestCase;
use App\Tests\Html;
use App\Tests\TempDir;
use DOMDocument;
use DOMNode;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Страницы целиком на тестовой базе.
 */
final class PagesTest extends DatabaseTestCase
{
    // Части страниц ищутся по смыслу разметки, а не по CSS-классам: классы поменяются при вёрстке
    private const BREADCRUMBS = '//nav[@aria-label="Хлебные крошки"]//li';
    private const SORT = '//nav[@aria-label="Сортировка статей"]';
    private const PAGINATION = '//nav[@aria-label="Страницы"]';
    // Статья на своей странице, в отличие от карточек похожих статей, - с заголовком h1
    private const POST = '//main//article[header/h1]';

    // Приложение работает с тем же соединением и видит фикстуры из незакоммиченной транзакции
    protected bool $rollBack = true;

    private static TempDir $root;
    private Site $site;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Один корень на класс: шаблоны компилируются один раз, а не в каждом тесте
        self::$root = Site::root();
    }

    public static function tearDownAfterClass(): void
    {
        self::$root->remove();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->site = new Site(self::$root, fn (): PDO => $this->pdo);
    }

    protected function tearDown(): void
    {
        $this->site->close();
        parent::tearDown();
    }

    public function testHomePage(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $this->insertCategory('empty', 'Пустая');
        $agents = $this->insertCategory('agents', 'Агенты');
        $this->insertPost('both', [$agents, $tools], '2026-10-01 10:00:00');
        foreach (['a', 'b', 'c'] as $day => $slug) {
            $this->insertPost($slug, [$tools], '2026-10-0' . ($day + 2) . ' 12:00:00');
        }

        $page = $this->page($this->site->get('/'));

        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//header//nav//a'));
        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//main//h2'));
        self::assertSame(
            ['/category/tools', '/category/agents'],
            $page->attributes('//main//a[text()="Все статьи"]', 'href'),
        );
        self::assertSame(
            ['/post/c', '/post/b', '/post/a', '/post/both'],
            $page->attributes('//main//article//h3/a', 'href'),
        );
        // Сразу грузится только первая картинка, остальные - по мере прокрутки
        self::assertSame(['high', '', '', ''], $page->attributes('//main//img', 'fetchpriority'));
        self::assertSame(['', 'lazy', 'lazy', 'lazy'], $page->attributes('//main//img', 'loading'));
        self::assertSame('http://blog.test/', $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertArrayHasKey('WebSite', $page->schema());
    }

    public function testHomePageWithoutPosts(): void
    {
        $this->insertCategory('empty');

        $page = $this->page($this->site->get('/'));

        self::assertSame(0, $page->count('//header//nav'));
        self::assertSame(1, $page->count('//main//p[.="Статей пока нет."]'));
    }

    public function testHeadIsHandledLikeGet(): void
    {
        $this->insertCategoryWithPosts(1);

        self::assertSame(200, $this->site->request('HEAD', '/')->status);
        self::assertSame(200, $this->site->request('HEAD', '/category/tools')->status);
    }

    public function testCategoryPage(): void
    {
        $this->insertCategoryWithPosts(10);

        $page = $this->page($this->site->get('/category/tools'));

        self::assertSame('Инструменты', $page->text('//h1'));
        self::assertSame(['Главная', 'Инструменты'], $page->texts(self::BREADCRUMBS));
        // Девять на странице, сначала новые
        self::assertSame(
            ['/post/post-10', '/post/post-9', '/post/post-8', '/post/post-7', '/post/post-6',
                '/post/post-5', '/post/post-4', '/post/post-3', '/post/post-2'],
            $page->attributes('//main//article//h2/a', 'href'),
        );
        self::assertSame(['Новые'], $page->texts(self::SORT . '/a[@aria-current]'));
        self::assertSame(['/category/tools', '/category/tools?sort=views'], $page->attributes(self::SORT . '/a', 'href'));
        self::assertSame(['1'], $page->texts(self::PAGINATION . '//a[@aria-current="page"]'));
        self::assertSame('/category/tools?page=2', $page->attribute(self::PAGINATION . '//a[@rel="next"]', 'href'));
        self::assertSame('http://blog.test/category/tools', $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertSame(0, $page->count('//meta[@name="robots"]'));
        self::assertSame(['Инструменты'], $page->texts('//header//nav//a[@aria-current="page"]'));
        self::assertSame(['CollectionPage', 'BreadcrumbList'], array_keys($page->schema()));
    }

    public function testCategorySecondPage(): void
    {
        $this->insertCategoryWithPosts(10);

        $page = $this->page($this->site->get('/category/tools?page=2'));

        self::assertSame(['/post/post-1'], $page->attributes('//main//article//h2/a', 'href'));
        self::assertSame('Инструменты, страница 2 — Агентура', $page->text('//title'));
        self::assertSame('http://blog.test/category/tools?page=2', $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertSame('/category/tools', $page->attribute(self::PAGINATION . '//a[@rel="prev"]', 'href'));
        self::assertSame(0, $page->count(self::PAGINATION . '//a[@rel="next"]'));
    }

    public function testCategorySortedByViews(): void
    {
        $this->insertCategoryWithPosts(10);

        $page = $this->page($this->site->get('/category/tools?sort=views'));

        // Просмотров больше у старых статей фикстуры
        self::assertSame('/post/post-1', $page->attribute('//main//article//h2/a', 'href'));
        self::assertSame(['Популярные'], $page->texts(self::SORT . '/a[@aria-current]'));
        self::assertSame('/category/tools?sort=views&page=2', $page->attribute(self::PAGINATION . '//a[@rel="next"]', 'href'));
        self::assertSame('noindex, follow', $page->attribute('//meta[@name="robots"]', 'content'));
    }

    public function testEmptyCategory(): void
    {
        $this->insertCategory('empty');

        $page = $this->page($this->site->get('/category/empty'));

        self::assertSame(1, $page->count('//main//p[.="В этой категории пока нет статей."]'));
        self::assertSame(0, $page->count('//main//article'));
        self::assertSame(0, $page->count(self::PAGINATION));
    }

    public function testPostPage(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        $agents = $this->insertCategory('agents', 'Агенты');
        $this->insertCategory('empty', 'Пустая');
        $this->insertPost('main', [$agents, $tools], '2026-10-09 16:33:00', title: 'Главная статья', content: <<<'TEXT'
            Вступление.

            ## Раздел

            - первый пункт
            - второй пункт
            TEXT);
        $this->insertPost('both', [$tools, $agents], '2026-09-01 10:00:00');
        $this->insertPost('newer', [$tools], '2026-10-05 10:00:00');
        $this->insertPost('older', [$agents], '2026-10-01 10:00:00');
        $this->insertPost('oldest', [$agents], '2026-08-01 10:00:00');

        $page = $this->page($this->site->get('/post/main'));

        self::assertSame('Главная статья', $page->text('//h1'));
        self::assertSame(['Главная', 'Инструменты', 'Главная статья'], $page->texts(self::BREADCRUMBS));
        self::assertSame(['/category/tools', '/category/agents'], $page->attributes(self::POST . '/header//a', 'href'));
        self::assertSame(['Вступление.'], $page->texts(self::POST . '//p[not(ancestor::header)]'));
        self::assertSame(['Раздел'], $page->texts(self::POST . '//h2'));
        self::assertSame(['первый пункт', 'второй пункт'], $page->texts(self::POST . '//li[not(ancestor::header)]'));
        self::assertSame('high', $page->attribute('//article/img', 'fetchpriority'));
        // Похожие: больше общих категорий, затем новее
        self::assertSame(['/post/both', '/post/newer', '/post/older'], $page->attributes('//aside//article//h3/a', 'href'));
        self::assertSame(['Инструменты', 'Агенты'], $page->texts('//aside//section[h2="Категории"]//a'));
        self::assertSame('http://blog.test/post/main', $page->attribute('//link[@rel="canonical"]', 'href'));
        self::assertSame('article', $page->attribute('//meta[@property="og:type"]', 'content'));
        self::assertSame('http://blog.test/uploads/main.webp', $page->attribute('//meta[@property="og:image"]', 'content'));
        self::assertSame(['BlogPosting', 'BreadcrumbList'], array_keys($page->schema()));
    }

    public function testPostTextIsEscaped(): void
    {
        $this->insertPost(
            'xss',
            [$this->insertCategory('tools')],
            title: '<b>Заголовок</b>',
            content: "<script>alert(1)</script>\n\n## <img src=x onerror=alert(1)>\n\n- <a href=\"javascript:alert(1)\">пункт</a>",
        );

        $response = $this->site->get('/post/xss');
        $page = $this->page($response);

        self::assertSame('<b>Заголовок</b>', $page->text('//h1'));
        self::assertSame(0, $page->count('//main//script | //main//img[@src="x"] | //main//a[starts-with(@href, "javascript")]'));
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body);
    }

    public function testViewsAreCountedOnGetOnly(): void
    {
        $this->insertPost('post', [$this->insertCategory('tools')], views: 1999);

        self::assertSame(200, $this->site->request('HEAD', '/post/post')->status);
        self::assertSame(1999, $this->views('post'));

        $page = $this->page($this->site->get('/post/post'));

        self::assertSame(2000, $this->views('post'));
        self::assertSame('Просмотры: 2 000', $page->text(self::POST . '/header//span[span="Просмотры:"]'));
    }

    public function testSitemap(): void
    {
        $tools = $this->insertCategory('tools');
        $this->insertCategory('empty');
        $this->insertPost('old', [$tools], '2026-09-01 10:00:00');
        $this->insertPost('new', [$tools], '2026-10-05 10:00:00');

        $response = $this->site->get('/sitemap.xml');

        self::assertSame(200, $response->status);
        self::assertSame('application/xml; charset=utf-8', $response->headers['Content-Type']);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($response->body));
        $locs = array_map(fn (DOMNode $node): string => $node->textContent, iterator_to_array($document->getElementsByTagName('loc')));
        self::assertSame([
            'http://blog.test/',
            'http://blog.test/category/tools',
            'http://blog.test/post/new',
            'http://blog.test/post/old',
        ], $locs);
    }

    public function testFeed(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        for ($day = 1; $day <= 22; $day++) {
            $this->insertPost("post-{$day}", [$tools], sprintf('2026-10-%02d 10:00:00', $day));
        }

        $response = $this->site->get('/feed.xml');

        self::assertSame(200, $response->status);
        self::assertSame('application/rss+xml; charset=utf-8', $response->headers['Content-Type']);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($response->body));
        $items = $document->getElementsByTagName('item');
        // 20 последних статей, от новых к старым
        self::assertSame(20, $items->length);
        self::assertSame('http://blog.test/post/post-22', $items->item(0)?->getElementsByTagName('link')->item(0)?->textContent);
        self::assertSame('http://blog.test/post/post-3', $items->item(19)?->getElementsByTagName('link')->item(0)?->textContent);
        self::assertSame('Инструменты', $document->getElementsByTagName('category')->item(0)?->textContent);
    }

    public function testLlmsTxt(): void
    {
        $tools = $this->insertCategory('tools', 'Инструменты', 'Обзоры сервисов.');
        $this->insertCategory('empty', 'Пустая');
        $this->insertPost('first', [$tools], title: 'Первая');

        $response = $this->site->get('/llms.txt');

        self::assertSame(200, $response->status);
        self::assertSame('text/plain; charset=utf-8', $response->headers['Content-Type']);
        self::assertStringStartsWith("# Агентура\n\n> Блог об ИИ.\n", $response->body);
        self::assertStringContainsString("## Инструменты\n", $response->body);
        self::assertStringContainsString('- [Инструменты: все статьи](http://blog.test/category/tools): Обзоры сервисов.', $response->body);
        self::assertStringContainsString('- [Первая](http://blog.test/post/first): Описание статьи first.', $response->body);
        self::assertStringContainsString('(http://blog.test/feed.xml)', $response->body);
        self::assertStringNotContainsString('Пустая', $response->body);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function redirects(): array
    {
        return [
            'первая страница' => ['/category/tools?page=1', '/category/tools'],
            'сортировка по умолчанию' => ['/category/tools?sort=date', '/category/tools'],
            'обе по умолчанию' => ['/category/tools?sort=date&page=1', '/category/tools'],
            'вторая страница' => ['/category/tools?sort=date&page=2', '/category/tools?page=2'],
            'первая по просмотрам' => ['/category/tools?page=1&sort=views', '/category/tools?sort=views'],
        ];
    }

    #[DataProvider('redirects')]
    public function testDefaultParametersRedirectToCanonicalAddress(string $url, string $location): void
    {
        $this->insertCategoryWithPosts(10);

        $response = $this->site->get($url);

        self::assertSame(301, $response->status);
        self::assertSame($location, $response->headers['Location']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function missingPages(): array
    {
        return [
            'неизвестная категория' => ['/category/missing'],
            'slug в другом регистре' => ['/category/Tools'],
            'страница после последней' => ['/category/tools?page=3'],
            'нулевая страница' => ['/category/tools?page=0'],
            'страница не числом' => ['/category/tools?page=abc'],
            'неизвестная сортировка' => ['/category/tools?sort=rating'],
            'неизвестная категория с первой страницей' => ['/category/missing?page=1'],
            'неизвестная статья' => ['/post/missing'],
            'slug статьи в другом регистре' => ['/post/POST-1'],
            'кириллица в адресе статьи' => ['/post/привет'],
            'кириллица в адресе категории' => ['/category/привет'],
            'закодированная кириллица' => ['/post/%D0%BF%D1%80%D0%B8%D0%B2%D0%B5%D1%82'],
            'символ вне ascii' => ['/category/%E2%82%AC'],
            'пробел в slug' => ['/post/post%201'],
        ];
    }

    #[DataProvider('missingPages')]
    public function testMissingPagesGet404(string $url): void
    {
        $this->insertCategoryWithPosts(10);

        self::assertSame(404, $this->site->get($url)->status);
    }

    /**
     * Категория tools со статьями post-1 ... post-N: чем новее статья, тем меньше у неё просмотров.
     */
    private function insertCategoryWithPosts(int $count): int
    {
        $tools = $this->insertCategory('tools', 'Инструменты');
        for ($number = 1; $number <= $count; $number++) {
            $this->insertPost("post-{$number}", [$tools], sprintf('2026-10-%02d 10:00:00', $number), views: 1000 - $number);
        }

        return $tools;
    }

    private function views(string $slug): mixed
    {
        $statement = $this->pdo->prepare('SELECT views FROM posts WHERE slug = ?');
        $statement->execute([$slug]);

        return $statement->fetchColumn();
    }

    private function page(Response $response): Html
    {
        self::assertSame('', $this->site->errorLog());
        self::assertSame(200, $response->status);

        return new Html($response->body);
    }
}
