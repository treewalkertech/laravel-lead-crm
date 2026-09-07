<x-admin::layouts>
    <!-- Page Title -->
    <x-slot:title>
        @lang('admin::app.prospects.create.title')
    </x-slot>

    {!! view_render_event('admin.prospects.create.form.before') !!}

    <x-admin::form
        :action="route('admin.prospects.store')"
        method="POST"
    >
        <div class="flex flex-col gap-4">
            <div class="scroll-reactive-sticky sticky top-[60px] z-[1000] flex items-center justify-between rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.prospects.create.breadcrumbs.before') !!}

                    <!-- Breadcrumbs -->
                    <x-admin::breadcrumbs name="prospects.create" />

                    {!! view_render_event('admin.prospects.create.breadcrumbs.after') !!}

                    <div class="text-xl font-bold dark:text-gray-300">
                        @lang('admin::app.prospects.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <div class="flex items-center gap-x-2.5">
                        {!! view_render_event('admin.prospects.create.save_button.before') !!}

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('admin::app.prospects.create.save-btn')
                        </button>

                        {!! view_render_event('admin.prospects.create.save_button.after') !!}
                    </div>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.prospects.create.form_controls.before') !!}

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            @lang('admin::app.prospects.create.name')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="name"
                            id="name"
                            rules="required"
                            :label="trans('admin::app.prospects.create.name')"
                            :placeholder="trans('admin::app.prospects.create.name')"
                            value="{{ old('name') }}"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.prospects.create.industry')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            name="industry"
                            id="industry"
                            :label="trans('admin::app.prospects.create.industry')"
                            :placeholder="trans('admin::app.prospects.create.industry')"
                            value="{{ old('industry') }}"
                        />

                        <x-admin::form.control-group.error control-name="industry" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            @lang('admin::app.prospects.create.source')
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            name="prospect_source_id"
                            id="prospect_source_id"
                            :label="trans('admin::app.prospects.create.source')"
                            :value="old('prospect_source_id')"
                        >
                            <option value="">@lang('admin::app.prospects.create.source-placeholder')</option>

                            @foreach ($sources as $source)
                                <option value="{{ $source->id }}">
                                    {{ $source->name }}
                                </option>
                            @endforeach
                        </x-admin::form.control-group.control>

                        <x-admin::form.control-group.error control-name="prospect_source_id" />
                    </x-admin::form.control-group>
                </div>

                {!! view_render_event('admin.prospects.create.form_controls.after') !!}
            </div>

            <div class="box-shadow rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                {!! view_render_event('admin.prospects.create.contacts.before') !!}

                <v-prospect-contacts :initial-contacts="[]"></v-prospect-contacts>

                {!! view_render_event('admin.prospects.create.contacts.after') !!}
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.prospects.create.form.after') !!}

    @include('admin::prospects.contacts')
</x-admin::layouts>
