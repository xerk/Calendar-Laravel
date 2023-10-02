<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>GrandCalendar</title>

    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <script src="https://cdn.tailwindcss.com"></script>

    <script>
        setTimeout(() => {
            window.location.href = '{{ config('saas.app_url') . '/dashboard/plans?status='.@$request->respStatus }}&message={{ @$request->respMessage }}';
        }, 5000);
    </script>
</head>
<body class="h-full">
    <main class="grid min-h-full place-items-center bg-white py-24 px-6 sm:py-32 lg:px-8">
        <div class="text-center">
          <div class="text-center font-semibold text-indigo-600">
            <img width="100" src="{{ config('saas.app_logo_url') }}" class="logo mx-auto" alt="{{ config('app.name') }} Logo">          
          </div>
          <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-2xl">{{ @$request->respMessage }}</h1>
          <p class="mt-6 text-base leading-7 text-gray-600">Redirecting.. Please click the button below if you are not redirected within a few seconds</p>
          <div class="mt-10 flex items-center justify-center gap-x-6">
            <a href="{{ config('saas.app_url') . '/dashboard/plans?status='.@$request->respStatus }}&message={{ @$request->respMessage }}" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Continue</a>
            <a href="mailto:{{ config('saas.support_email') }}?subject=Payment Support (CODE: {{ @$request->respCode }})" class="text-sm font-semibold text-gray-900">Contact support <span aria-hidden="true">&rarr;</span></a>
          </div>
        </div>
      </main>
</body>
</html>