<?php

declare(strict_types=1);

use IvanBaric\Taxonomy\Actions\AttachTaxonomyItemAction;
use IvanBaric\Taxonomy\Actions\CreateTaxonomyAction;
use IvanBaric\Taxonomy\Actions\DeleteTaxonomyAction;
use IvanBaric\Taxonomy\Actions\DetachTaxonomyItemAction;
use IvanBaric\Taxonomy\Actions\SyncTaxonomyItemsAction;
use IvanBaric\Taxonomy\Actions\UpdateTaxonomyAction;
use IvanBaric\Taxonomy\Models\Taxonomy;
use IvanBaric\Taxonomy\Tests\Fixtures\Models\Post;

it('creates updates and deletes a taxonomy through actions', function (): void {
    $created = app(CreateTaxonomyAction::class)->handle([
        'name' => 'Categories',
        'type' => 'blog',
    ]);

    expect($created->success)->toBeTrue()
        ->and($created->data)->toBeInstanceOf(Taxonomy::class);

    $taxonomy = $created->data;
    $updated = app(UpdateTaxonomyAction::class)->handle($taxonomy, [
        'name' => 'Post categories',
        'lock_version' => $taxonomy->lock_version,
    ]);

    expect($updated->success)->toBeTrue()
        ->and($taxonomy->refresh()->name)->toBe('Post categories');

    $deleted = app(DeleteTaxonomyAction::class)->handle($taxonomy);

    expect($deleted->success)->toBeTrue()
        ->and(Taxonomy::query()->count())->toBe(0);
});

it('attaches syncs and detaches items through locked action flows', function (): void {
    $taxonomy = Taxonomy::create(['name' => 'Categories', 'type' => 'blog']);
    $news = $taxonomy->items()->create(['name' => 'News']);
    $updates = $taxonomy->items()->create(['name' => 'Updates']);
    $post = Post::create(['title' => 'Action flow']);

    $attached = app(AttachTaxonomyItemAction::class)->handle($post, 'blog', $news);

    expect($attached->success)->toBeTrue()
        ->and($attached->data['attached_item_ids'])->toBe([$news->id]);

    $synced = app(SyncTaxonomyItemsAction::class)->handle($post, 'blog', [$updates]);

    expect($synced->success)->toBeTrue()
        ->and($synced->data['attached_item_ids'])->toBe([$updates->id])
        ->and($synced->data['detached_item_ids'])->toBe([$news->id])
        ->and($post->taxonomyItemIds('blog'))->toBe([$updates->id]);

    $detached = app(DetachTaxonomyItemAction::class)->handle($post, 'blog');

    expect($detached->success)->toBeTrue()
        ->and($detached->data['detached_item_ids'])->toBe([$updates->id])
        ->and($post->taxonomyItemIds('blog'))->toBe([]);
});
