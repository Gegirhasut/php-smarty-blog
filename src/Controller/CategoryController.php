<?php

declare(strict_types=1);

namespace App\Controller;

use App\Blog\ListingQuery;
use App\Blog\Sort;
use App\Core\NotFoundException;
use App\Core\Pagination;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Seo\PageMeta;

final class CategoryController
{
    // Сетка карточек 3×3
    private const PER_PAGE = 9;

    public function __construct(
        private readonly View $view,
        private readonly PageMeta $meta,
        private readonly CategoryRepository $categories,
        private readonly PostRepository $posts,
    ) {
    }

    public function show(Request $request, string $slug): Response
    {
        $category = $this->categories->findBySlug($slug) ?? throw new NotFoundException();
        $query = ListingQuery::parse($request->query);
        $path = "/category/{$category->slug}";

        if (!$query->isCanonical($request->query)) {
            return Response::redirect($query->url($path));
        }

        $pagination = new Pagination($query->page, $this->posts->countInCategory($category->id), self::PER_PAGE);
        if (!$pagination->exists()) {
            throw new NotFoundException();
        }

        return new Response($this->view->render('category.tpl', [
            'meta' => $this->meta->category($category, $query),
            'categories' => $this->categories->withPosts(),
            'currentCategory' => $category->slug,
            'category' => $category,
            'posts' => $this->posts->inCategory($category->id, $query->sort, self::PER_PAGE, $pagination->offset()),
            // Смена сортировки возвращает на первую страницу
            'sorts' => array_map(fn (Sort $sort): array => [
                'label' => $sort->label(),
                'url' => (new ListingQuery($sort))->url($path),
                'current' => $sort === $query->sort,
            ], Sort::cases()),
            'pagination' => $pagination->links(fn (int $page): string => $query->withPage($page)->url($path)),
        ]));
    }
}
