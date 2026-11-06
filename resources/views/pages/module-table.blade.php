@php($hasStatus = collect($modules)->contains(fn ($module) => isset($module['status'])))
<div class="overflow-x-auto"><table class="w-full"><thead><tr><th scope="col">Module</th><th scope="col">Version</th><th scope="col">Capabilities</th>@if ($hasStatus)<th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Problems</th>@endif</tr></thead><tbody>
@foreach ($modules as $module)<tr><th scope="row">{{ $module['display_name'] }}</th><td>{{ $module['version'] }}</td><td>{{ implode(', ', $module['capabilities']) }}</td>@if ($hasStatus)<td>{{ $module['kind'] ?? '' }}</td><td>{{ $module['status'] ?? '' }}</td><td>{{ implode(' ', $module['problems'] ?? []) }}</td>@endif</tr>@endforeach
</tbody></table></div>
