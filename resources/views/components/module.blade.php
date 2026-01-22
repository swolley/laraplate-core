@props(['module', 'data'])

<div class="module {{ ! $data['isEnabled'] ? 'disabled' : '' }} flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
	@if(! $data['isEnabled'])
		<x-core::cancel-icon style="color: var(--color-gray-600);" />
	@endif

	<div class="w-full flex flex-col h-full">
		<div class="flex items-center justify-between mb-2">
			<h3 class="text-lg font-semibold text-black dark:text-white">{{ $module }}</h3>
			<span class="text-xs text-gray-500 dark:text-gray-400">{{ $data['version'] ?? 'N/A' }}</span>
		</div>

		@if(isset($data['description']))
			<p class="text-sm text-gray-600 dark:text-gray-300 mb-4">{{ $data['description'] }}</p>
		@endif

		<div class="my-4 text-sm/relaxed flex grow">
			{{-- models --}}
			<div class="w-1/2 flex flex-col gap-2">
				<div class="flex items-center">
					<x-core::barcode-icon style="color: var(--primary-400);" />
					<div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">Models</div>
				</div>

				<div class="ml-12">
					@if ($data['models'] === [])
					<div class="mt-2 text-sm">
						<span class="text-gray-700">No Model found</span>
					</div>
					@else
					@foreach ($data['models'] as $model)
					<div class="mt-2 text-sm">
						<span class="text-gray-600 dark:text-gray-400">{{ Str::afterLast($model, '\\') }}</span>
					</div>
					@endforeach
					@endif
				</div>
			</div>

			{{-- controllers --}}
			<div class="w-1/2 flex flex-col gap-2">
				<div class="flex items-center">
					<x-core::route-icon style="color: var(--primary-400);" />
					<div class="ml-4 text-lg leading-7 font-semibold text-gray-900 dark:text-white">Controllers</div>
				</div>

				<div class="ml-12">
					@if ($data['controllers'] === [])
					<div class="mt-2 text-sm">
						<span class="text-gray-700">No Controller found</span>
					</div>
					@else
					@foreach ($data['controllers'] as $controller)
					<div class="mt-2 text-sm">
						<span class="text-gray-600 dark:text-gray-400">{{ Str::afterLast($controller, '\\') }}</span>
					</div>
					@endforeach
					@endif
				</div>
			</div>
		</div>

		@if ($data['authors'] && $data['authors'] !== [])
			<p class="mt-4 text-sm/relaxed text-gray-600 dark:text-gray-300">
				@foreach ($data['authors'] as $author)
					<span class="author">
						<span>{{ $author['name'] }}</span>
						@if ($author['email'])
							<a href="mailto:{{ $author['email'] }}">({{ $author['email'] }})</a>
						@endif
					</span>
				@endforeach
			</p>
		@endif

	</div>
</div>