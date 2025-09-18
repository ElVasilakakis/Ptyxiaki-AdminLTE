<div class="page-header page-header-light shadow">
	
	@yield('pageheader')

	<div class="page-header-content d-lg-flex border-top">
		<div class="d-flex">
			<div class="breadcrumb py-2">
				<a href="{{ route('app.dashboard') }}" class="breadcrumb-item">
					<i class="ph-house"></i>
				</a>
				
				@php
					$routeName = Route::currentRouteName();
					$segments = explode('.', $routeName);
				@endphp
				
				@if($routeName !== 'app.dashboard')
					@if(in_array('lands', $segments))
						<a href="{{ route('app.lands.index') }}" class="breadcrumb-item">Lands</a>
						@if($routeName !== 'app.lands.index')
							<span class="breadcrumb-item active">{{ ucfirst(last($segments)) }}</span>
						@else
							<span class="breadcrumb-item active">All Lands</span>
						@endif
					@elseif(in_array('devices', $segments))
						<a href="{{ route('app.devices.index') }}" class="breadcrumb-item">Devices</a>
						@if($routeName !== 'app.devices.index')
							<span class="breadcrumb-item active">{{ ucfirst(last($segments)) }}</span>
						@else
							<span class="breadcrumb-item active">All Devices</span>
						@endif
					@elseif(in_array('sensors', $segments))
						<a href="{{ route('app.sensors.index') }}" class="breadcrumb-item">Sensors</a>
						@if($routeName !== 'app.sensors.index')
							<span class="breadcrumb-item active">{{ ucfirst(last($segments)) }}</span>
						@else
							<span class="breadcrumb-item active">All Sensors</span>
						@endif
					@elseif(in_array('mqttbrokers', $segments))
						<a href="{{ route('app.mqttbrokers.index') }}" class="breadcrumb-item">Connectors</a>
						@if($routeName !== 'app.mqttbrokers.index')
							<span class="breadcrumb-item active">{{ ucfirst(last($segments)) }}</span>
						@else
							<span class="breadcrumb-item active">All Brokers</span>
						@endif
					@else
						<span class="breadcrumb-item active">{{ ucfirst(last($segments)) }}</span>
					@endif
				@else
					<span class="breadcrumb-item active">Dashboard</span>
				@endif
			</div>

			<a href="#breadcrumb_elements"
				class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
				data-bs-toggle="collapse">
				<i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
			</a>
		</div>

		{{-- <div class="collapse d-lg-block ms-lg-auto" id="breadcrumb_elements">
			<div class="d-lg-flex mb-2 mb-lg-0">
				<a href="#" class="d-flex align-items-center text-body py-2">
					Link
				</a>

				<div class="dropdown ms-lg-3">
					<a href="#" class="d-flex align-items-center text-body dropdown-toggle py-2"
						data-bs-toggle="dropdown">
						<span class="flex-1">Actions</span>
					</a>

					<div class="dropdown-menu dropdown-menu-end w-100 w-lg-auto">
						<a href="#" class="dropdown-item">Add New</a>
						<a href="#" class="dropdown-item">Export</a>
						<a href="#" class="dropdown-item">Settings</a>
						<div class="dropdown-divider"></div>
						<a href="#" class="dropdown-item">Help</a>
					</div>
				</div>
			</div>
		</div> --}}
	</div>

</div>
