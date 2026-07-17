<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Iniciar sesión</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen grid place-items-center bg-[#eef1f6] p-4"><main class="w-full max-w-md rounded-2xl bg-white p-8 shadow-xl shadow-[#004479]/10">
    <div class="mb-7 text-center"><span class="mx-auto grid size-14 place-items-center rounded-2xl bg-[#004479] text-[#ffcc00] font-bold text-xl">CC</span><h1 class="mt-4 text-2xl font-semibold text-[#004479]">Bienvenido</h1><p class="text-sm text-slate-500">Ingresa a tu panel de atención</p></div>
    <x-flash /><form method="POST" action="{{ route('login.store') }}" class="space-y-4">@csrf
        <label class="block text-sm font-medium">Correo<input name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:ring-2 focus:ring-[#004479]/30"></label>
        <label class="block text-sm font-medium">Contraseña<input name="password" type="password" required class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 outline-none focus:ring-2 focus:ring-[#004479]/30"></label>
        <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1"> Recordarme</label>
        <button class="w-full rounded-xl bg-[#004479] px-4 py-3 font-medium text-white hover:bg-[#00345c]">Iniciar sesión</button>
    </form>
</main></body></html>
