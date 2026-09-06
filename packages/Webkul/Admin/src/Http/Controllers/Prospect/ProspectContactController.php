<?php

namespace Webkul\Admin\Http\Controllers\Prospect;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\SourceRepository;
use Webkul\Lead\Repositories\TypeRepository;
use Webkul\Prospect\Repositories\ProspectContactRepository;

class ProspectContactController extends Controller
{
    /**
     * The name used to find-or-create the lead source/type attributed to a converted prospect,
     * since both are required (NOT NULL) columns on the leads table but a prospect carries
     * neither.
     */
    const CONVERSION_LABEL = 'Prospect Conversion';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected ProspectContactRepository $prospectContactRepository,
        protected OrganizationRepository $organizationRepository,
        protected PersonRepository $personRepository,
        protected LeadRepository $leadRepository,
        protected PipelineRepository $pipelineRepository,
        protected SourceRepository $sourceRepository,
        protected TypeRepository $typeRepository,
    ) {}

    /**
     * Convert a prospect contact into a real Organization + Person + Lead.
     */
    public function convertToLead(int $id): JsonResponse
    {
        $contact = $this->prospectContactRepository->findOrFail($id);

        $this->preventUnauthorizedAccess($contact->prospect->user_id);

        if ($contact->converted_lead_id) {
            return response()->json([
                'message' => trans('admin::app.prospects.contacts.already-converted'),
            ], 400);
        }

        Event::dispatch('prospect.contact.convert.before', $contact);

        $ownerId = $contact->prospect->user_id ?? auth()->guard('user')->id();

        $organization = $this->organizationRepository->findOneWhere(['name' => $contact->prospect->name])
            ?: $this->organizationRepository->create([
                'name' => $contact->prospect->name,
                'user_id' => $ownerId,
            ]);

        $contactNumbers = collect([$contact->mobile, $contact->phone])
            ->filter()
            ->unique()
            ->map(fn ($number) => ['value' => $number, 'label' => 'work'])
            ->values()
            ->all();

        $person = $this->personRepository->create([
            'entity_type' => 'persons',
            'name' => $contact->name,
            'emails' => $contact->email ? [['value' => $contact->email, 'label' => 'work']] : [],
            'contact_numbers' => $contactNumbers,
            'job_title' => $contact->designation,
            'organization_id' => $organization->id,
            'user_id' => $ownerId,
        ]);

        $pipeline = $this->pipelineRepository->getDefaultPipeline();

        $stage = $pipeline->stages()->first();

        $source = $this->sourceRepository->findOneWhere(['name' => self::CONVERSION_LABEL])
            ?: $this->sourceRepository->create(['name' => self::CONVERSION_LABEL]);

        $type = $this->typeRepository->findOneWhere(['name' => self::CONVERSION_LABEL])
            ?: $this->typeRepository->create(['name' => self::CONVERSION_LABEL]);

        $lead = $this->leadRepository->create([
            'title' => trans('admin::app.prospects.contacts.converted-lead-title', [
                'contact' => $contact->name,
                'organization' => $contact->prospect->name,
            ]),
            'person_id' => $person->id,
            'user_id' => $ownerId,
            'lead_source_id' => $source->id,
            'lead_type_id' => $type->id,
            'lead_pipeline_id' => $pipeline->id,
            'lead_pipeline_stage_id' => $stage?->id,
        ]);

        $this->prospectContactRepository->update([
            'converted_lead_id' => $lead->id,
        ], $contact->id);

        Event::dispatch('prospect.contact.convert.after', $contact, $lead);

        return response()->json([
            'message' => trans('admin::app.prospects.contacts.convert-success'),
            'lead_id' => $lead->id,
        ]);
    }
}
