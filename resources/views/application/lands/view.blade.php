@extends('layouts.application.app')

@section('pageheader')
    <div class="page-header-content d-lg-flex">
        <div class="d-flex">
            <h4 class="page-title mb-0">
                Lands - <span class="fw-normal">View,Manage and Create Lands</span>
            </h4>

            <a href="#page_header"
                class="btn btn-light align-self-center collapsed d-lg-none border-transparent rounded-pill p-0 ms-auto"
                data-bs-toggle="collapse">
                <i class="ph-caret-down collapsible-indicator ph-sm m-1"></i>
            </a>
        </div>

        <div class="collapse d-lg-block my-lg-auto ms-lg-auto" id="page_header">
            <div class="hstack gap-3 mb-3 mb-lg-0">
                <button type="button" class="btn btn-primary">
                    Create
                    <i class="ph-plus-circle ms-2"></i>
                </button>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="content">

       <h1>Lands Index</h1>

    </div>
@endsection
