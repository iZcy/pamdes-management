{{-- resources/views/super-admin/dashboard.blade.php --}}
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Dashboard - PAMDes</title>
  @vite(['resources/css/app.css'])
</head>

<body class="bg-gray-100">
  <div class="min-h-screen">
    <header class="bg-gray-800 text-white py-4">
      <div class="container mx-auto px-4">
        <h1 class="text-2xl font-bold">PAMDes Super Admin Dashboard</h1>
        <nav class="mt-2">
          <a href="{{ url('/admin') }}" class="text-gray-300 hover:text-white mr-4">Filament Admin</a>
          <a href="{{ route('super.villages') }}" class="text-gray-300 hover:text-white">Manage Villages</a>
        </nav>
      </div>
    </header>

    <main class="container mx-auto px-4 py-8">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach ($villages as $village)
          <div class="bg-white rounded-lg shadow-md p-6">
            <h3 class="text-xl font-bold mb-2">{{ $village->name }}</h3>
            <div class="text-sm text-gray-600 space-y-1">
              <p>Pelanggan: {{ $village->customers_count ?? 0 }}</p>
              <p>Status: {{ $village->is_active ? 'Aktif' : 'Tidak Aktif' }}</p>
            </div>
            <div class="mt-4 space-x-2">
              {{-- <a href="{{ village_url($village->slug) }}"
                class="inline-block bg-blue-600 text-white px-3 py-1 rounded text-sm hover:bg-blue-700">
                Visit Site
              </a>
              <a href="{{ village_url($village->slug, 'admin') }}"
                class="inline-block bg-green-600 text-white px-3 py-1 rounded text-sm hover:bg-green-700">
                Admin Panel
              </a> --}}
            </div>
          </div>
        @endforeach
      </div>
    </main>
  </div>
</body>

</html>
