<?php

namespace Webkul\Admin\Http\Controllers\Contact;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\View\View;
use Webkul\Admin\DataGrids\Contact\OrganizationDataGrid;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Admin\Http\Requests\AttributeForm;
use Webkul\Admin\Http\Requests\MassDestroyRequest;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;

class OrganizationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrganizationRepository $organizationRepository,
        protected PersonRepository $personRepository
    ) {
        request()->request->add(['entity_type' => 'organizations']);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): View|JsonResponse
    {
        if (request()->ajax()) {
            return datagrid(OrganizationDataGrid::class)->process();
        }

        return view('admin::contacts.organizations.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin::contacts.organizations.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AttributeForm $request): RedirectResponse|JsonResponse
    {
        $this->validateContacts();

        Event::dispatch('contacts.organization.create.before');

        $organization = $this->organizationRepository->create(request()->all());

        $this->createContacts($organization);

        Event::dispatch('contacts.organization.create.after', $organization);

        if (request()->ajax()) {
            return response()->json([
                'data' => $organization,
                'message' => trans('admin::app.contacts.organizations.index.create-success'),
            ]);
        }

        session()->flash('success', trans('admin::app.contacts.organizations.index.create-success'));

        return redirect()->route('admin.contacts.organizations.index');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(int $id): View
    {
        $organization = $this->organizationRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($organization->user_id);

        return view('admin::contacts.organizations.edit', compact('organization'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AttributeForm $request, int $id): RedirectResponse
    {
        $this->preventUnauthorizedAccess($this->organizationRepository->findOrFail($id)->user_id);

        $this->validateContacts();

        Event::dispatch('contacts.organization.update.before', $id);

        $organization = $this->organizationRepository->update(request()->all(), $id);

        $this->createContacts($organization);

        Event::dispatch('contacts.organization.update.after', $organization);

        session()->flash('success', trans('admin::app.contacts.organizations.index.update-success'));

        return redirect()->route('admin.contacts.organizations.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id): JsonResponse
    {
        $this->preventUnauthorizedAccess($this->organizationRepository->findOrFail($id)->user_id);

        try {
            Event::dispatch('contact.organization.delete.before', $id);

            $this->organizationRepository->delete($id);

            Event::dispatch('contact.organization.delete.after', $id);

            return response()->json([
                'message' => trans('admin::app.contacts.organizations.index.delete-success'),
            ], 200);
        } catch (\Exception $exception) {
            return response()->json([
                'message' => trans('admin::app.contacts.organizations.index.delete-failed'),
            ], 400);
        }
    }

    /**
     * Mass Delete the specified resources.
     */
    public function massDestroy(MassDestroyRequest $massDestroyRequest): JsonResponse
    {
        $organizations = $this->filterAuthorizedRecords(
            $this->organizationRepository->findWhereIn('id', $massDestroyRequest->input('indices'))
        );

        foreach ($organizations as $organization) {
            Event::dispatch('contact.organization.delete.before', $organization);

            $this->organizationRepository->delete($organization->id);

            Event::dispatch('contact.organization.delete.after', $organization);
        }

        return response()->json([
            'message' => trans('admin::app.contacts.organizations.index.delete-success'),
        ]);
    }

    /**
     * Validate the inline "contacts" rows submitted alongside the organization form. A row left
     * entirely blank is fine (it's just ignored in createContacts()); a row with only one of
     * name/email filled in is a mistake and should be reported back on that field, same as any
     * other form validation error.
     */
    protected function validateContacts(): void
    {
        request()->validate([
            'contacts' => 'nullable|array',
            'contacts.*.name' => 'required_with:contacts.*.email|nullable|string|max:100',
            'contacts.*.email' => 'required_with:contacts.*.name|nullable|email',
            'contacts.*.contact_number' => 'nullable|string|max:20',
        ]);
    }

    /**
     * Create a Person for each inline contact row that has at least a name and an email, linked to
     * the given organization. Reuses `PersonRepository::create()` exactly as the person create form
     * and the leads bulk importer already do, so EAV attribute values are saved consistently.
     */
    protected function createContacts($organization): void
    {
        $contacts = collect(request('contacts', []))
            ->filter(fn ($contact) => ! empty($contact['name']) && ! empty($contact['email']));

        foreach ($contacts as $contact) {
            $this->personRepository->create([
                'entity_type' => 'persons',
                'name' => $contact['name'],
                'emails' => [['value' => $contact['email'], 'label' => 'work']],
                'contact_numbers' => ! empty($contact['contact_number'])
                    ? [['value' => $contact['contact_number'], 'label' => 'work']]
                    : [],
                'organization_id' => $organization->id,
                'user_id' => $organization->user_id ?? auth()->guard('user')->id(),
            ]);
        }
    }
}
