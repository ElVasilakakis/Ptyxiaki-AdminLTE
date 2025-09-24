<!DOCTYPE html>
<html lang="en" dir="ltr" class="layout-static">
@include('layouts.application.head')

<body>

	<!-- Main navbar -->
	@include('layouts.application.topbar')
	<!-- /main navbar -->


	<!-- Page content -->
	<div class="page-content">

		<!-- Main sidebar -->
		@include('layouts.application.sidebar')
		<!-- /main sidebar -->


		<!-- Main content -->
		<div class="content-wrapper">

			<!-- Inner content -->
			<div class="content-inner">

				<!-- Page header -->
				@include('layouts.application.pageheader')
				<!-- /page header -->


				<!-- Content area -->
				@yield('content')
				<!-- /content area -->


				<!-- Footer -->
				@include('layouts.application.footer')
				<!-- /footer -->

			</div>
			<!-- /inner content -->

		</div>
		<!-- /main content -->
        @include('layouts.application.demo_config')
	</div>
	<!-- /page content -->

	@yield('scripts')
</body>
</html>
