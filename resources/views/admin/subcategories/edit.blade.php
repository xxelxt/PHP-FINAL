@extends('admin.layout.index')
@section('content')
<div class="card" style="border: none; margin: 30px;">
    <div class="row align-items-center">
        <div class="col">
            <h1>@lang('lang.sub')</h1>
            <p class="text-muted">@lang('lang.edit') thông tin</p>
        </div>
    </div>
</div>
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

    <form action="admin/subcategories/edit/{!! $subcategories['id'] !!}" method="POST">
        @csrf
        <div class="row mb-3">
            <label class="col-md-1 col-form-label">@lang('lang.cate')</label>
            <div class="col-md-11">
                <select name="cat_id" class="form-control form-control-primary">
                    @foreach ($categories as $value)
                    <option @if ($subcategories['categories']['id']==$value['id']) selected @endif value="{!! $value['id'] !!}">{!! $value['name'] !!}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="row mb-3">
            <label class="col-md-1 col-form-label">@lang('lang.name')</label>
            <div class="col-md-11">
                <input type="text" value="{!! $subcategories['name'] !!}" class="form-control" name="name" placeholder="Nhập tên danh mục" required>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-11 offset-md-1">
                <button type="submit" class="btn btn-primary">@lang('lang.update')</button>
            </div>
        </div>
    </form>
</div>
@endsection