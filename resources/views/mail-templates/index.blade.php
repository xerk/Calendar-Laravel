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
                    <div class="px-4 sm:px-6 lg:px-8">
                        <div class="sm:flex sm:items-center">
                            {{-- Display success alery --}}
                            @if (session('status'))
                            <div class="text-sm border border-t-8 rounded text-green-600 border-green-600 bg-green-100 px-3 py-4 mb-4" role="alert">
                                {{ session('status') }}
                            </div>
                            @endif
                          <div class="sm:flex-auto">
                            <h1 class="text-base font-semibold leading-6 text-gray-900  dark:text-gray-200">Users</h1>
                            <p class="mt-2 text-sm text-gray-700 dark:text-gray-200">A list of all the users in your account including their name, title, email and role.</p>
                          </div>
                          <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                            <x-primary-button
                                x-data=""
                                x-on:click.prevent="$dispatch('open-modal', 'mailable')"
                            >{{ __('New Maillable') }}</x-primary-button>

                            <x-modal name="mailable" :show="$errors->isNotEmpty()" focusable>
                                <form method="post" action="{{ route('mailable.create') }}" class="p-6">
                                    @csrf

                                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        {{ __('Create Mail Template') }}
                                    </h2>

                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ __('Please fill in the form below to create a new mail template.') }}
                                    </p>

                                    <div class="mt-6">
                                        <x-input-label
                                            for="mailable" :value="__('Mailable')"  class="sr-only" />
                                        <x-text-input id="mailable" name="mailable" type="text" class="mt-1 block w-full" :value="old('mailable')" required autofocus autocomplete="off"
                                            placeholder="ex: ExampleMail" />
                                        <x-input-error class="mt-2" :messages="$errors->get('mailable')" />

                                    </div>

                                    <div class="mt-6 flex justify-end">
                                        <x-secondary-button x-on:click="$dispatch('close')">
                                            {{ __('Cancel') }}
                                        </x-secondary-button>

                                        <x-primary-button class="ml-3">
                                            {{ __('Create') }}
                                        </x-primary-button>
                                    </div>
                                </form>
                            </x-modal>
                          </div>
                        </div>
                        <div class="mt-8 flow-root">
                          <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                            <div class="inline-block w-full py-2 align-middle sm:px-6 lg:px-8">
                              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">

                                <table class="w-full divide-y divide-gray-300">
                                  <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                      <th scope="col" class="py-2 pl-4 pr-2 text-left text-sm font-semibold text-gray-900 dark:text-gray-100 sm:pl-6">Maillable</th>
                                      <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900 dark:text-gray-100">Created At</th>
                                      <th scope="col" class="relative py-2 pl-3 pr-4 sm:pr-6">
                                        <span class="sr-only">Edit</span>
                                      </th>
                                    </tr>
                                  </thead>
                                  <tbody class="divide-y divide-gray-200 dark:divide-gray-800 bg-white dark:bg-gray-800">
                                      @if (count($mailTemplates) > 0)
                                      @foreach ($mailTemplates as $mailTemplate)
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-gray-100 sm:pl-6">{{ $mailTemplate->mailable }}</td>
                                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-100">{{ $mailTemplate->created_at->diffForHumans() }}</td>
                                            <td class="relative whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <a href="{{ url("/mail/edit/$mailTemplate->id") }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-500 dark:hover:text-indigo-400">Edit<span class="sr-only">, Lindsay Walton</span></a>
                                            </td>
                                        </tr>
                                        @endforeach
                                        @else
                                        <tr>
                                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-gray-100 sm:pl-6 text-center" colspan="4">
                                                <p class="text-gray-500 dark:text-gray-100 pb-4">No mailables found.</p>
                                                <a href="{{ url('/mail/create') }}" class="rounded-md bg-indigo-600 px-3 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                                <svg class="w-6 h-6 inline-block text-gray-400 dark:text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>Add Maillable</a>
                                            </td>
                                        </tr>
                                        @endif
                                  </tbody>
                                </table>
                              </div>
                            </div>
                          </div>
                        </div>
                      </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
