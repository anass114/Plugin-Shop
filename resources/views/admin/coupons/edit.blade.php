@extends('admin.layouts.admin')

@section('title', trans('shop::admin.coupons.edit', ['coupon' => $coupon->name]))

@section('content')
    <div class="card shadow mb-4">
        <div class="card-body">
            <form action="{{ route('shop.admin.coupons.update', $coupon) }}" method="POST">
                @method('PUT')

                @include('shop::admin.coupons._form')

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save"></i> {{ trans('messages.actions.save') }}</button>
                <a href="{{ route('shop.admin.coupons.destroy', $coupon) }}" class="btn btn-danger" data-confirm="delete"><i class="bi bi-trash"></i> {{ trans('messages.actions.delete') }}
                </a>
            </form>
        </div>
    </div>
@endsection
