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
                            {{ __('Edit Mail Template ' . $mailTemplate->mailable) }}
                        </h2>

                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Please fill in the form below to create a new mail template.') }}
                        </p>
                    </header>

                    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
                        @csrf
                        @method('patch')

                        <div>
                            {{--
                            <x-input-label for="mailable" :value="__('Mailable')" /> --}}
                            {{-- <select id="mailable" name="mailable" x-data="{ show: false }"
                                x-on:change="show = true"
                                class="mt-1 block w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                :value="old('mailable')" required autofocus autocomplete="off">
                                @foreach ($mails as $mail)
                                <option value="ExampleMail">{{ $mail }}</option>
                                @endforeach
                            </select> --}}
                            {{-- <x-secondary-button class="my-2">{{ __('New Mail') }}</x-secondary-button> --}}

                            {{--
                            <x-text-input id="mailable" name="mailable" type="text" class="mt-1 block w-full"
                                :value="old('mailable')" required autofocus autocomplete="off"
                                placeholder="ex: ExampleMail" /> --}}
                            <x-input-error class="mt-2" :messages="$errors->get('name')" />
                        </div>
                        {{-- if mailable selected --}}
                        <div class="text-gray-800" x-show="show" x-transition>
                            <x-input-label for="email" class="mb-3" :value="__('Subject')" />
                            <div id="subject"></div>
                            <x-input-error class="mt-2" :messages="$errors->get('subject')" />
                        </div>

                        <div class="text-gray-800">
                            <x-input-label for="email" class="mb-3" :value="__('Body')" />
                            <div id="body"></div>
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="pills-home" role="tabpanel"
                                    aria-labelledby="pills-home-tab">
                                    <textarea id="template_editor" cols="30"
                                        rows="10">{{ $skeleton['template'] }}</textarea>
                                </div>
                                <div class="tab-pane fade" id="pills-profile" role="tabpanel"
                                    aria-labelledby="pills-profile-tab">
                                    <textarea id="plain_text" cols="30" rows="10"></textarea>
                                </div>
                            </div>
                            <x-input-error class="mt-2" :messages="$errors->get('Body')" />
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>

                            @if (session('status') === 'profile-updated')
                            <p x-data="{ show: true }" x-show="show" x-transition
                                x-init="setTimeout(() => show = false, 2000)"
                                class="text-sm text-gray-600 dark:text-gray-400">{{ __('Saved.') }}</p>
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

        })
        .catch( error => {
            console.error( error );
        });
    ClassicEditor
        .create( document.querySelector( '#body' ), {
            // text dark mode

        } )
        .catch( error => {
            console.error( error );
        });
</script>

<style type="text/css">
    .CodeMirror {
        height: 400px;
    }

    .editor-preview-active,
    .editor-preview-active-side {
        /*display:block;*/
    }

    .editor-preview-side>p,
    .editor-preview>p {
        margin: inherit;
    }

    .editor-preview pre,
    .editor-preview-side pre {
        background: inherit;
        margin: inherit;
    }

    .editor-preview table td,
    .editor-preview table th,
    .editor-preview-side table td,
    .editor-preview-side table th {
        border: inherit;
        padding: inherit;
    }

    .view_data_param {
        cursor: pointer;
    }
</style>

<script>
    var simplemde = new SimpleMDE(
                {
                element: $("#template_editor")[0],
                toolbar: [
                    {
                            name: "bold",
                            action: SimpleMDE.toggleBold,
                            className: "fa fa-bold",
                            title: "Bold",
                    },
                    {
                            name: "italic",
                            action: SimpleMDE.toggleItalic,
                            className: "fa fa-italic",
                            title: "Italic",
                    },
                    {
                            name: "strikethrough",
                            action: SimpleMDE.toggleStrikethrough,
                            className: "fa fa-strikethrough",
                            title: "Strikethrough",
                    },
                    {
                            name: "heading",
                            action: SimpleMDE.toggleHeadingSmaller,
                            className: "fa fa-header",
                            title: "Heading",
                    },
                    {
                            name: "code",
                            action: SimpleMDE.toggleCodeBlock,
                            className: "fa fa-code",
                            title: "Code",
                    },

                    "|",
                    {
                            name: "unordered-list",
                            action: SimpleMDE.toggleBlockquote,
                            className: "fa fa-list-ul",
                            title: "Generic List",
                    },
                    {
                            name: "uordered-list",
                            action: SimpleMDE.toggleOrderedList,
                            className: "fa fa-list-ol",
                            title: "Numbered List",
                    },
                    {
                            name: "clean-block",
                            action: SimpleMDE.cleanBlock,
                            className: "fa fa-eraser fa-clean-block",
                            title: "Clean block",
                    },
                    "|",
                    {
                            name: "link",
                            action: SimpleMDE.drawLink,
                            className: "fa fa-link",
                            title: "Create Link",
                    },
                    {
                            name: "image",
                            action: SimpleMDE.drawImage,
                            className: "fa fa-picture-o",
                            title: "Insert Image",
                    },
                    {
                            name: "horizontal-rule",
                            action: SimpleMDE.drawHorizontalRule,
                            className: "fa fa-minus",
                            title: "Insert Horizontal Line",
                    },
                    "|",
                    {
                        name: "button-component",
                        action: setButtonComponent,
                        className: "fa fa-hand-pointer-o",
                        title: "Button Component",
                    },
                    {
                        name: "table-component",
                        action: setTableComponent,
                        className: "fa fa-table",
                        title: "Table Component",
                    },
                    {
                        name: "promotion-component",
                        action: setPromotionComponent,
                        className: "fa fa-bullhorn",
                        title: "Promotion Component",
                    },
                    {
                        name: "panel-component",
                        action: setPanelComponent,
                        className: "fa fa-thumb-tack",
                        title: "Panel Component",
                    },
                    "|",
                    {
                            name: "side-by-side",
                            action: SimpleMDE.toggleSideBySide,
                            className: "fa fa-columns no-disable no-mobile",
                            title: "Toggle Side by Side",
                    },
                    {
                            name: "fullscreen",
                            action: SimpleMDE.toggleFullScreen,
                            className: "fa fa-arrows-alt no-disable no-mobile",
                            title: "Toggle Fullscreen",
                    },
                    {
                            name: "preview",
                            action: SimpleMDE.togglePreview,
                            className: "fa fa-eye no-disable",
                            title: "Toggle Preview",
                    },
                ],
                renderingConfig: { singleLineBreaks: true, codeSyntaxHighlighting: true,},
                hideIcons: ["guide"],
                spellChecker: false,
                promptURLs: true,
                placeholder: "Write your Beautiful Email",

                previewRender: function(plainText, preview) {
                     // return preview.innerHTML = 'sacas';
                    $.ajax({
                          method: "POST",
                          url: "{{ route('previewTemplateMarkdownView') }}",
                          data: { markdown: plainText, name: 'new' }

                    }).done(function( HtmledTemplate ) {
                        preview.innerHTML = HtmledTemplate;
                    });

                    return '';
                },

            });

            function setButtonComponent(editor) {

                link = prompt('Button Link');

                var cm = editor.codemirror;
                var output = '';
                var selectedText = cm.getSelection();
                var text = selectedText || 'Button Text';

                output = `
[component]: # ('mail::button',  ['url' => '`+ link +`'])
` + text + `
[endcomponent]: #
                `;
                cm.replaceSelection(output);

            }

            function setPromotionComponent(editor) {

                var cm = editor.codemirror;
                var output = '';
                var selectedText = cm.getSelection();
                var text = selectedText || 'Promotion Text';

                output = `
[component]: # ('mail::promotion')
` + text + `
[endcomponent]: #
                `;
                cm.replaceSelection(output);

            }

            function setPanelComponent(editor) {

                var cm = editor.codemirror;
                var output = '';
                var selectedText = cm.getSelection();
                var text = selectedText || 'Panel Text';

                output = `
[component]: # ('mail::panel')
` + text + `
[endcomponent]: #
                `;
                cm.replaceSelection(output);

            }

            function setTableComponent(editor) {

                var cm = editor.codemirror;
                var output = '';
                var selectedText = cm.getSelection();

                output = `
[component]: # ('mail::table')
| Laravel       | Table         | Example  |
| ------------- |:-------------:| --------:|
| Col 2 is      | Centered      | $10      |
| Col 3 is      | Right-Aligned | $20      |
[endcomponent]: #
                `;
                cm.replaceSelection(output);

            }

            $('.preview-toggle').click(function(){
                simplemde.togglePreview();
                $(this).toggleClass('active');
            });

</script>


{{-- @endpush --}}
