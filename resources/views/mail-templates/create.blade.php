<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <header>
                        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                            {{ __('Create Mail Template') }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Please fill in the form below to create a new mail template.') }}
                        </p>
                    </header>

                    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
                        @csrf
                        @method('patch')

                        <div>
                            <x-input-label
                            for="mailable" :value="__('Mailable')" />
                            <select id="mailable" name="mailable"
                                x-data="{ show: false }"
                                x-on:change="show = true"
                                class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                            :value="old('mailable')" required autofocus autocomplete="off">
                            @foreach ($mails as $mail)
                            <option value="ExampleMail">{{ $mail }}</option>
                            @endforeach
                            </select>
                            {{-- <x-secondary-button class="my-2">{{ __('New Mail') }}</x-secondary-button> --}}

                            {{-- <x-text-input id="mailable" name="mailable" type="text" class="mt-1 block w-full" :value="old('mailable')" required autofocus autocomplete="off"
                                placeholder="ex: ExampleMail" /> --}}
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>
                        {{-- if mailable selected --}}
                        <div class="text-gray-800"
                            x-show="show"
                            x-transition
                        >
                            <x-input-label for="email" :value="__('Subject')" />
                            <div id="subject"></div>
                            <x-input-error class="mt-2" :messages="$errors->get('subject')" />
                        </div>

                        <div class="text-gray-800">
                            <x-input-label for="email" :value="__('Body')" />
                            <div id="body"></div>
                            <x-input-error class="mt-2" :messages="$errors->get('Body')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>

                            @if (session('status') === 'profile-updated')
                                <p
                                    x-data="{ show: true }"
                                    x-show="show"
                                    x-transition
                                    x-init="setTimeout(() => show = false, 2000)"
                                    class="text-sm text-gray-600 dark:text-gray-400"
                                >{{ __('Saved.') }}</p>
                            @endif
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>

{{-- @push('scripts') --}}
<script src="https://cdn.ckeditor.com/ckeditor5/38.1.1/classic/ckeditor.js" type="module"></script>
<script type="module">
    ClassicEditor
        .create( document.querySelector( '#subject' ), {
            // text dark mode

        } )
        .catch( error => {
            console.error( error );
        } );
    ClassicEditor
        .create( document.querySelector( '#body' ), {
            // text dark mode

        } )
        .catch( error => {
            console.error( error );
        } );
</script>
{{-- @endpush --}}
