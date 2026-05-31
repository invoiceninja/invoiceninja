<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Factory\TagFactory;
use App\Filters\TagFilters;
use App\Http\Requests\Tag\ActionTagRequest;
use App\Http\Requests\Tag\CreateTagRequest;
use App\Http\Requests\Tag\DestroyTagRequest;
use App\Http\Requests\Tag\EditTagRequest;
use App\Http\Requests\Tag\IndexTagRequest;
use App\Http\Requests\Tag\ShowTagRequest;
use App\Http\Requests\Tag\StoreTagRequest;
use App\Http\Requests\Tag\UpdateTagRequest;
use App\Models\Tag;
use App\Repositories\TagRepository;
use App\Transformers\TagTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class TagController extends BaseController
{
    use MakesHash;

    protected $entity_type = Tag::class;

    protected $entity_transformer = TagTransformer::class;

    protected TagRepository $tag_repo;

    public function __construct(TagRepository $tag_repo)
    {
        parent::__construct();

        $this->tag_repo = $tag_repo;
    }

    /**
     * Index. Always requires `entity_type` filter (FQCN) because tags are
     * scoped per taggable entity type.
     */
    public function index(IndexTagRequest $request, TagFilters $filters)
    {
        $tags = Tag::filter($filters);

        return $this->listResponse($tags);
    }

    public function create(CreateTagRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $tag = TagFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($tag);
    }

    public function store(StoreTagRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $tag = TagFactory::create($user->company()->id, $user->id);
        $tag->fill($request->all());
        $tag->save();

        return $this->itemResponse($tag->fresh());
    }

    public function show(ShowTagRequest $request, Tag $tag)
    {
        return $this->itemResponse($tag);
    }

    public function edit(EditTagRequest $request, Tag $tag)
    {
        return $this->itemResponse($tag);
    }

    public function update(UpdateTagRequest $request, Tag $tag)
    {
        $tag->fill($request->all());
        $tag->save();

        return $this->itemResponse($tag->fresh());
    }

    public function destroy(DestroyTagRequest $request, Tag $tag)
    {
        $this->tag_repo->delete($tag);

        return $this->itemResponse($tag->fresh());
    }

    public function bulk(ActionTagRequest $request)
    {
        $action = $request->input('action');
        $ids = $request->input('ids');

        Tag::withTrashed()
            ->company()
            ->whereIn('id', $this->transformKeys($ids))
            ->cursor()
            ->each(function ($tag) use ($action) {
                $this->tag_repo->{$action}($tag);
            });

        return $this->listResponse(Tag::withTrashed()->company()->whereIn('id', $this->transformKeys($ids)));
    }
}
