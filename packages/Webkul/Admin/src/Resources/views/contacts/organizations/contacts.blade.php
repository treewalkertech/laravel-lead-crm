{{--
    Shared "add contacts inline" component for the Create/Edit Organization forms. Included from
    both create.blade.php and edit.blade.php so the Vue component definition isn't duplicated.
--}}
@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-organization-contacts-template"
    >
        <div class="flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <p class="text-base font-semibold text-gray-800 dark:text-white">
                    @lang('admin::app.contacts.organizations.create.contacts-title')
                </p>

                <p class="text-sm text-gray-600 dark:text-white">
                    @lang('admin::app.contacts.organizations.create.contacts-info')
                </p>
            </div>

            <template v-for="(contact, index) in contacts" :key="index">
                <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-gray-300 p-4 dark:border-gray-800 sm:grid-cols-4">
                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.contacts.organizations.create.contact-name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            ::id="`contacts[${index}][name]`"
                            ::name="`contacts[${index}][name]`"
                            v-model="contact.name"
                            :label="trans('admin::app.contacts.organizations.create.contact-name')"
                        />

                        <x-admin::form.control-group.error ::name="`contacts[${index}][name]`" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.contacts.organizations.create.contact-email')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="email"
                            ::id="`contacts[${index}][email]`"
                            ::name="`contacts[${index}][email]`"
                            v-model="contact.email"
                            rules="email"
                            :label="trans('admin::app.contacts.organizations.create.contact-email')"
                        />

                        <x-admin::form.control-group.error ::name="`contacts[${index}][email]`" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group class="!mb-0">
                        <x-admin::form.control-group.label>
                            @lang('admin::app.contacts.organizations.create.contact-phone')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            ::id="`contacts[${index}][contact_number]`"
                            ::name="`contacts[${index}][contact_number]`"
                            v-model="contact.contact_number"
                            :label="trans('admin::app.contacts.organizations.create.contact-phone')"
                        />

                        <x-admin::form.control-group.error ::name="`contacts[${index}][contact_number]`" />
                    </x-admin::form.control-group>

                    <div class="flex h-full items-center sm:justify-end sm:pt-6">
                        <i
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

                @lang('admin::app.contacts.organizations.create.add-contact')
            </span>
        </div>
    </script>

    <script type="module">
        app.component('v-organization-contacts', {
            template: '#v-organization-contacts-template',

            data() {
                return {
                    contacts: [],
                };
            },

            methods: {
                addContact() {
                    this.contacts.push({
                        name: '',
                        email: '',
                        contact_number: '',
                    });
                },

                removeContact(index) {
                    this.contacts.splice(index, 1);
                },
            },
        });
    </script>
@endPushOnce
