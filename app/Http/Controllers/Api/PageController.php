<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\PageResource;
use App\Models\Post;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Display a listing of CMS Pages.
     */
    public function index()
    {
        $pages = Post::page()->published()->with(['translations', 'meta'])->latest()->get();

        return PageResource::collection($pages);
    }

    /**
     * Display the specified CMS Page by slug.
     */
    public function show(string $slug)
    {
        $page = Post::page()
            ->published()
            ->where('slug', $slug)
            ->with(['translations', 'meta', 'media'])
            ->firstOrFail();

        return new PageResource($page);
    }

    /**
     * Display a listing of Blog Posts.
     */
    public function posts()
    {
        $posts = Post::post()->published()->with(['translations', 'meta'])->latest()->get();

        return PageResource::collection($posts);
    }

    /**
     * Display the specified Blog Post by slug.
     */
    public function showPost(string $slug)
    {
        $post = Post::post()
            ->published()
            ->where('slug', $slug)
            ->with(['translations', 'meta', 'media'])
            ->firstOrFail();

        return new PageResource($post);
    }
}
