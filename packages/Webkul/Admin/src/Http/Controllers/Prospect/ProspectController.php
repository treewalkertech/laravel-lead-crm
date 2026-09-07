<?php

namespace Webkul\Admin\Http\Controllers\Prospect;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Prospect\ProspectDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Prospect\Models\ProspectContact;
use Webkul\Prospect\Repositories\ProspectRepository;
use Webkul\Prospect\Repositories\ProspectSourceRepository;

class ProspectController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ProspectRepository $prospectRepository,
        protected ProspectSourceRepository $prospectSourceRepository
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(ProspectDataGrid::class)->process();
        }

        return view('admin::prospects.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $sources = $this->prospectSourceRepository->all();

        return view('admin::prospects.create', compact('sources'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->validateRequest();

        Event::dispatch('prospect.create.before');

        $prospect = $this->prospectRepository->create(array_merge(request()->all(), [
            'user_id' => request('user_id') ?: auth()->guard('user')->id(),
        ]));

        Event::dispatch('prospect.create.after', $prospect);

        session()->flash('success', trans('admin::app.prospects.index.create-success'));

        return redirect()->route('admin.prospects.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $prospect = $this->prospectRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($prospect->user_id);

        $sources = $this->prospectSourceRepository->all();

        return view('admin::prospects.edit', compact('prospect', 'sources'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        $prospect = $this->prospectRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($prospect->user_id);

        $this->validateRequest();

        Event::dispatch('prospect.update.before', $id);

        $prospect = $this->prospectRepository->update(request()->all(), $id);

        Event::dispatch('prospect.update.after', $prospect);

        session()->flash('success', trans('admin::app.prospects.index.update-success'));

        return redirect()->route('admin.prospects.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $prospect = $this->prospectRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($prospect->user_id);

        try {
            Event::dispatch('prospect.delete.before', $id);

            $this->prospectRepository->delete($id);

            Event::dispatch('prospect.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.prospects.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.prospects.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $prospects = $this->filterAuthorizedRecords(
            $this->prospectRepository->findWhereIn('id', $massDestroyRequest->input('indices'))
        );

        foreach ($prospects as $prospect) {
            Event::dispatch('prospect.delete.before', $prospect->id);

            $this->prospectRepository->delete($prospect->id);

            Event::dispatch('prospect.delete.after', $prospect->id);
        }

        return response()->json([
            'message' => trans('admin::app.prospects.index.delete-success'),
        ]);
    }

    /**
     * Validate the prospect and its inline contact rows. A contact row left entirely blank is
     * fine (it's just ignored by the repository); a row with a name but no way to reach the
     * person is still saved — the sales team can fill in contact details after the fact.
     */
    protected function validateRequest(): void
    {
        request()->validate([
            'name' => 'required|string|max:100',
            'industry' => 'nullable|string|max:100',
            'prospect_source_id' => 'nullable|exists:prospect_sources,id',
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'nullable|string|max:100',
            'contacts.*.mobile' => 'nullable|string|max:20',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.email' => 'nullable|email',
            'contacts.*.designation' => 'nullable|string|max:100',
            'contacts.*.department' => 'nullable|string|max:100',
            'contacts.*.status' => 'nullable|in:'.implode(',', [
                ProspectContact::STATUS_NEW,
                ProspectContact::STATUS_CALL_NOT_PICKED,
                ProspectContact::STATUS_CONTACTED,
                ProspectContact::STATUS_WRONG_NUMBER_EMAIL,
            ]),
            'contacts.*.comment' => 'nullable|string|max:1000',
        ]);
    }
}
