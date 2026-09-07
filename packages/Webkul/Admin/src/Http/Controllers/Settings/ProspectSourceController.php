<?php

namespace Webkul\Admin\Http\Controllers\Settings;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Settings\ProspectSourceDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Prospect\Repositories\ProspectSourceRepository;

class ProspectSourceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(protected ProspectSourceRepository $prospectSourceRepository) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ProspectSourceDataGrid::class)->process();
        }

        return view('admin::settings.prospect-sources.index');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(): JsonResponse
    {
        $this->validate(request(), [
            'name' => ['required', 'unique:prospect_sources,name'],
        ]);

        Event::dispatch('settings.prospect_source.create.before');

        $source = $this->prospectSourceRepository->create(request()->only(['name']));

        Event::dispatch('settings.prospect_source.create.after', $source);

        return new JsonResponse([
            'data' => $source,
            'message' => trans('admin::app.settings.prospect-sources.index.create-success'),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View|JsonResponse
    {
        $source = $this->prospectSourceRepository->findOrFail($id);

        return new JsonResponse([
            'data' => $source,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(int $id): JsonResponse
    {
        $this->validate(request(), [
            'name' => 'required|unique:prospect_sources,name,'.$id,
        ]);

        Event::dispatch('settings.prospect_source.update.before', $id);

        $source = $this->prospectSourceRepository->update(request()->only(['name']), $id);

        Event::dispatch('settings.prospect_source.update.after', $source);

        return new JsonResponse([
            'data' => $source,
            'message' => trans('admin::app.settings.prospect-sources.index.update-success'),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $source = $this->prospectSourceRepository->findOrFail($id);

        if ($source->prospects()->count() > 0) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.prospect-sources.index.delete-failed-associated-prospects'),
            ], 400);
        }

        try {
            Event::dispatch('settings.prospect_source.delete.before', $id);

            $source->delete();

            Event::dispatch('settings.prospect_source.delete.after', $id);

            return new JsonResponse([
                'message' => trans('admin::app.settings.prospect-sources.index.delete-success'),
            ], 200);
        } catch (Exception $exception) {
            return new JsonResponse([
                'message' => trans('admin::app.settings.prospect-sources.index.delete-failed'),
            ], 400);
        }
    }
}
