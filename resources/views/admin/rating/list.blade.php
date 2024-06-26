@extends('admin.layout.index')
@section('content')
@role('admin')
<div class="card" style="border: none; margin: 30px;">
    @if(count($errors)>0)
    <div class="alert alert-danger">
        @foreach($errors->all() as $arr)
        {{$arr}}<br>
        @endforeach
    </div>
    @endif
    @if (session('thongbao'))
    <div class="alert alert-success">
        {{session('thongbao')}}
    </div>
    @endif
    <div class="row align-items-center">
        <div class="col">
            <h1>@lang('lang.rating')</h1>
            <p class="text-muted">@lang('lang.list')</p>
        </div>
        <form action="{{ route('admin.ratings.list') }}" method="GET">
            <div class="input-group" style="margin-top: 20px; margin-right: 200px; padding-right: 15px;">
                <input type="text" class="form-control" name="search" placeholder="@lang('lang.search')" value="{{ request('search') }}">
                <button class="btn btn-outline-secondary" type="submit">@lang('lang.search')</button>
            </div>
        </form>
    </div>
</div>

<div class="card" style="border: none; margin: 30px;">
    <div class="table-responsive">
        <table id="autofill" class="table table-bordered">
            <thead>
                <tr align="center">
                    <th>ID</th>
                    <th>@lang('lang.name')</th>
                    <th>Email</th>
                    <th>@lang('lang.products')</th>
                    <th>@lang('lang.rating')</th>
                    <th>@lang('lang.created')</th>
                    <th>@lang('lang.delete')</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rating as $value)
                <tr align="center">
                    <td>{!! $value['users']['id'] !!}</td>
                    <td>{!! $value['users']['lastname'] !!} {!! $value['users']['firstname'] !!}</td>
                    <td>{!! $value['users']['email'] !!}</td>
                    <td>{!! $value['products']['name'] !!}</td>
                    <td>{!! $value['ratings'] !!}</td>
                    <td>{!! $value['created_at']->timezone('Asia/Ho_Chi_Minh')->format('d-m-Y H:i:s') !!}</td>
                    <td><a href="javascript:void(0)" data-url="{{ url('ajax/delete_rating', $value['id'] ) }}" class="btn btn-danger delete-rating">@lang('lang.delete')</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@else
<h1 align="center">@lang('lang.deny')</h1>
@endrole
@section('script')
<script>
    //delete ajax 
    $(document).ready(function() {
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        $('.delete-rating').on('click', function() {
            var userURL = $(this).data('url');
            var trObj = $(this);
            if (confirm("Are you sure you want to remove it?") == true) {
                $.ajax({
                    url: userURL,
                    type: 'DELETE',
                    dataType: 'json',
                    success: function(data) {
                        if (data['success']) {
                            // alert(data.success);
                            trObj.parents("tr").remove();
                        } else if (data['error']) {
                            alert(data.error);
                        }
                    }
                });
            }

        });
    });
</script>
@endsection
@endsection