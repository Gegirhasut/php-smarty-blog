<?php

declare(strict_types=1);

namespace App\Controller;

use App\Blog\ArticleText;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\PageMeta;

final class PostController
{
    private const SIMILAR_POSTS = 3;

    public function __construct(
        private readonly View $view,
        private readonly PageMeta $meta,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function show(Request $request, string $slug): Response
    {
        $post = $this->posts->findBySlug($slug) ?? throw new NotFoundException();

        // HEAD шлют проверки ссылок и боты, а не читатели: такой запрос просмотром не считается
        if ($request->method === 'GET') {
            $this->posts->incrementViews($post->id);
            $post = $post->withViews($post->views + 1);
        }

        $categories = $this->categories->forPost($post->id);
        $text = new ArticleText($post->content);

        return new Response($this->view->render('post.tpl', [
            'meta' => $this->meta->post($post, $categories),
            'categories' => $this->categories->withPosts(),
            'post' => $post,
            'postCategories' => $categories,
            'blocks' => $text->blocks(),
            'readingMinutes' => $text->readingMinutes(),
            'similar' => $this->posts->similar($post->id, self::SIMILAR_POSTS),
        ]));
    }
}
