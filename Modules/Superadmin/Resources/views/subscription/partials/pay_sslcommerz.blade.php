<div class="col-md-12">
    <form method="POST" action="{{route('sslcommerz-init')}}">
        {{ csrf_field() }}
        <input type="hidden" name="package_id" value="{{$package->id}}">
        <input type="hidden" name="price" value="{{$package->price}}">
        <input type="hidden" name="coupon_code" value="{{request()->get('code') ?? null}}">
        
        <button type="submit" class="tw-dw-btn tw-dw-btn-success tw-text-white tw-dw-btn-sm" style="background-color: #E2136E; border-color: #E2136E;">
            <i class="fas fa-credit-card"></i> Pay via {{$v}}
        </button>
    </form>
    <p class="help-block">Securely pay using SSLCOMMERZ (Cards, bKash, Rocket, Nagad, Net Banking, etc.)</p>
</div>
