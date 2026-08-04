<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'create-client-page',
    ])
>
    <style>
        .create-client-page .create-client-shell {
            width: 100%;
            max-width: none;
            margin-inline: 0;
        }

        .create-client-page .create-client-intro {
            margin-bottom: 1.25rem;
            padding: 1rem 1.15rem;
            border-radius: 1rem;
            border: 1px solid rgba(245, 158, 11, 0.22);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.10), rgba(255, 255, 255, 0.55));
        }

        .dark .create-client-page .create-client-intro {
            border-color: rgba(245, 158, 11, 0.28);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.14), rgba(24, 24, 27, 0.55));
        }

        .create-client-page .create-client-intro__title {
            margin: 0;
            font-size: 0.95rem;
            font-weight: 700;
            color: rgb(120 53 15);
        }

        .dark .create-client-page .create-client-intro__title {
            color: rgb(253 230 138);
        }

        .create-client-page .create-client-intro__text {
            margin: 0.35rem 0 0;
            font-size: 0.875rem;
            line-height: 1.6;
            color: rgb(87 83 78);
        }

        .dark .create-client-page .create-client-intro__text {
            color: rgb(214 211 209);
        }

        .create-client-page .create-client-card {
            border-radius: 1.15rem;
            border: 1px solid rgba(214, 211, 209, 0.9);
            background: rgba(255, 255, 255, 0.92);
            box-shadow: 0 10px 30px rgba(28, 25, 23, 0.05);
            padding: 1.15rem 1.15rem 0.35rem;
        }

        .dark .create-client-page .create-client-card {
            border-color: rgba(63, 63, 70, 0.95);
            background: rgba(24, 24, 27, 0.72);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.22);
        }

        .create-client-page .fi-section {
            border-radius: 1rem;
        }

        .create-client-page .fi-section-header-heading {
            font-size: 1rem;
            font-weight: 700;
        }

        .create-client-page .fi-fo-radio .fi-fo-radio-option {
            border: 1px solid rgba(214, 211, 209, 0.95);
            border-radius: 0.9rem;
            padding: 0.85rem 0.95rem;
            background: rgba(250, 250, 249, 0.85);
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .dark .create-client-page .fi-fo-radio .fi-fo-radio-option {
            border-color: rgba(63, 63, 70, 0.95);
            background: rgba(39, 39, 42, 0.55);
        }

        .create-client-page .fi-fo-radio .fi-fo-radio-option:has(input:checked) {
            border-color: rgb(245 158 11);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.18);
            background: rgba(255, 251, 235, 0.95);
        }

        .dark .create-client-page .fi-fo-radio .fi-fo-radio-option:has(input:checked) {
            background: rgba(245, 158, 11, 0.12);
        }

        .create-client-page .create-client-actions {
            margin-top: 0.35rem;
            padding: 1rem 0 0.35rem;
            border-top: 1px solid rgba(231, 229, 228, 0.95);
        }

        .dark .create-client-page .create-client-actions {
            border-top-color: rgba(63, 63, 70, 0.95);
        }

        .create-client-page .create-client-actions .fi-btn {
            border-radius: 0.8rem;
            font-weight: 650;
        }
    </style>

    <div class="create-client-shell">
        <div class="create-client-intro">
            <p class="create-client-intro__title">{{ __('fields.create_client_intro_title') }}</p>
            <p class="create-client-intro__text">{{ __('fields.create_client_intro_text') }}</p>
        </div>

        <div class="create-client-card">
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="create"
            >
                {{ $this->form }}

                <div class="create-client-actions">
                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                    />
                </div>
            </x-filament-panels::form>
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
