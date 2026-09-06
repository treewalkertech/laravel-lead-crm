{{--
    Shared "contacts" repeater for the Create/Edit Prospect forms. Included from both blades so
    the Vue component definition isn't duplicated. Existing (already-saved) contacts carry an
    `id` and can be converted into a real Lead; newly added rows have no `id` yet and are created
    when the prospect form itself is saved.
--}}
@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-prospect-contacts-template"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.prospects.create.contacts-title')
                </p>

                <p class="text-sm text-gray-600 dark:text-white">
                    @lang('admin::app.prospects.create.contacts-info')
                </p>
            </div>

            <template v-for="(contact, index) in contacts" :key="contact.id ?? `new-${index}`">
                <div class="flex flex-col gap-4 rounded-lg border border-gray-300 p-4 dark:border-gray-800">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label class="required">
                                @lang('admin::app.prospects.create.contact-name')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][name]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][name]`"
                                v-model="contact.name"
                                :label="trans('admin::app.prospects.create.contact-name')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][name]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-email')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="email"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][email]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][email]`"
                                v-model="contact.email"
                                rules="email"
                                :label="trans('admin::app.prospects.create.contact-email')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][email]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-mobile')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][mobile]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][mobile]`"
                                v-model="contact.mobile"
                                :label="trans('admin::app.prospects.create.contact-mobile')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][mobile]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-phone')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][phone]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][phone]`"
                                v-model="contact.phone"
                                :label="trans('admin::app.prospects.create.contact-phone')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][phone]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-designation')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][designation]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][designation]`"
                                v-model="contact.designation"
                                :label="trans('admin::app.prospects.create.contact-designation')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][designation]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-department')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="text"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][department]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][department]`"
                                v-model="contact.department"
                                :label="trans('admin::app.prospects.create.contact-department')"
                            />

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][department]`" />
                        </x-admin::form.control-group>

                        <x-admin::form.control-group class="!mb-0">
                            <x-admin::form.control-group.label>
                                @lang('admin::app.prospects.create.contact-status')
                            </x-admin::form.control-group.label>

                            <x-admin::form.control-group.control
                                type="select"
                                ::id="`contacts[${contact.id ?? ('new_' + index)}][status]`"
                                ::name="`contacts[${contact.id ?? ('new_' + index)}][status]`"
                                v-model="contact.status"
                                :label="trans('admin::app.prospects.create.contact-status')"
                            >
                                <option value="new">@lang('admin::app.prospects.status.new')</option>
                                <option value="call_not_picked">@lang('admin::app.prospects.status.call-not-picked')</option>
                                <option value="contacted">@lang('admin::app.prospects.status.contacted')</option>
                                <option value="wrong_number_email">@lang('admin::app.prospects.status.wrong-number-email')</option>
                            </x-admin::form.control-group.control>

                            <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][status]`" />
                        </x-admin::form.control-group>
                    </div>

                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.prospects.create.contact-comment')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="textarea"
                            ::id="`contacts[${contact.id ?? ('new_' + index)}][comment]`"
                            ::name="`contacts[${contact.id ?? ('new_' + index)}][comment]`"
                            v-model="contact.comment"
                            :label="trans('admin::app.prospects.create.contact-comment')"
                            rows="2"
                        />

                        <x-admin::form.control-group.error ::name="`contacts[${contact.id ?? ('new_' + index)}][comment]`" />
                    </x-admin::form.control-group>

                    <div class="flex items-center justify-between">
                        <template v-if="contact.id">
                            <a
                                v-if="contact.converted_lead_id"
                                ::href="viewLeadUrl(contact.converted_lead_id)"
                                class="text-sm text-brandColor hover:underline"
                            >
                                @lang('admin::app.prospects.create.view-converted-lead')
                            </a>

                            <button
                                v-else
                                type="button"
                                class="text-sm text-brandColor hover:underline"
                                @click="convertToLead(contact)"
                                :disabled="contact.isConverting"
                            >
                                @lang('admin::app.prospects.create.convert-to-lead')
                            </button>
                        </template>

                        <span v-else></span>

                        <i
                            v-if="! contact.id"
                            class="icon-cross-large cursor-pointer text-2xl text-gray-600 hover:text-red-600"
                            @click="removeContact(index)"
                        ></i>
                    </div>
                </div>
            </template>

            <span
                class="text-md flex max-w-max cursor-pointer items-center gap-2 text-brandColor"
                @click="addContact"
            >
                <i class="icon-add text-md"></i>

                @lang('admin::app.prospects.create.add-contact')
            </span>
        </div>
    </script>

    <script type="module">
        app.component('v-prospect-contacts', {
            template: '#v-prospect-contacts-template',

            props: {
                initialContacts: {
                    type: Array,
                    default: () => [],
                },
            },

            data() {
                return {
                    contacts: this.initialContacts.map(contact => ({ ...contact })),
                };
            },

            methods: {
                addContact() {
                    this.contacts.push({
                        id: null,
                        name: '',
                        mobile: '',
                        phone: '',
                        email: '',
                        designation: '',
                        department: '',
                        status: 'new',
                        comment: '',
                        converted_lead_id: null,
                    });
                },

                removeContact(index) {
                    this.contacts.splice(index, 1);
                },

                viewLeadUrl(leadId) {
                    return "{{ route('admin.leads.view', '__LEAD_ID__') }}".replace('__LEAD_ID__', leadId);
                },

                convertToLead(contact) {
                    contact.isConverting = true;

                    this.$axios.put("{{ route('admin.prospects.contacts.convert', '__CONTACT_ID__') }}".replace('__CONTACT_ID__', contact.id))
                        .then((response) => {
                            contact.converted_lead_id = response.data.lead_id;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch((error) => {
                            this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        })
                        .finally(() => {
                            contact.isConverting = false;
                        });
                },
            },
        });
    </script>
@endPushOnce
