@extends('layouts.auth2')
@section('title', __('lang_v1.login'))
@inject('request', 'Illuminate\Http\Request')
@section('content')
    @php
        $username = old('username');
        $password = null;
        if (config('app.env') == 'demo') {
            $username = 'admin';
            $password = '123456';

            $demo_types = [
                'all_in_one' => 'admin',
                'super_market' => 'admin',
                'pharmacy' => 'admin-pharmacy',
                'electronics' => 'admin-electronics',
                'services' => 'admin-services',
                'restaurant' => 'admin-restaurant',
                'superadmin' => 'superadmin',
                'woocommerce' => 'woocommerce_user',
                'essentials' => 'admin-essentials',
                'manufacturing' => 'manufacturer-demo',
            ];

            if (!empty($_GET['demo_type']) && array_key_exists($_GET['demo_type'], $demo_types)) {
                $username = $demo_types[$_GET['demo_type']];
            }
        }
    @endphp
    <div class="row">
        <div class="col-md-4">
        @if (config('app.env') == 'demo')
        
                @component('components.widget', [
                    'class' => 'box-primary',
                    'header' =>
                        '<h4 class="text-center">Demo Shops <small><i> <br/>Demos are for example purpose only, this application <u>can be used in many other similar businesses.</u></i> <br/><b>Click button to login that business</b></small></h4>',
                ])
                    <a href="?demo_type=all_in_one" class="btn btn-app bg-olive demo-login" data-toggle="tooltip"
                        title="Showcases all feature available in the application."
                        data-admin="{{ $demo_types['all_in_one'] }}"> <i class="fas fa-star"></i> All In One</a>

                    <a href="?demo_type=pharmacy" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                        title="Shops with products having expiry dates." data-admin="{{ $demo_types['pharmacy'] }}"><i
                            class="fas fa-medkit"></i>Pharmacy</a>

                    <a href="?demo_type=services" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                        title="For all service providers like Web Development, Restaurants, Repairing, Plumber, Salons, Beauty Parlors etc."
                        data-admin="{{ $demo_types['services'] }}"><i class="fas fa-wrench"></i>Multi-Service Center</a>

                    <a href="?demo_type=electronics" class="btn bg-purple btn-app demo-login" data-toggle="tooltip"
                        title="Products having IMEI or Serial number code." data-admin="{{ $demo_types['electronics'] }}"><i
                            class="fas fa-laptop"></i>Electronics & Mobile Shop</a>

                    <a href="?demo_type=super_market" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                        title="Super market & Similar kind of shops." data-admin="{{ $demo_types['super_market'] }}"><i
                            class="fas fa-shopping-cart"></i> Super Market</a>

                    <a href="?demo_type=restaurant" class="btn bg-red btn-app demo-login" data-toggle="tooltip"
                        title="Restaurants, Salons and other similar kind of shops."
                        data-admin="{{ $demo_types['restaurant'] }}"><i class="fas fa-utensils"></i> Restaurant</a>
                    <hr>

                    <i class="icon fas fa-plug"></i> Premium optional modules:<br><br>

                    <a href="?demo_type=superadmin" class="btn bg-red-active btn-app demo-login" data-toggle="tooltip"
                        title="SaaS & Superadmin extension Demo" data-admin="{{ $demo_types['superadmin'] }}"><i
                            class="fas fa-university"></i> SaaS / Superadmin</a>

                    <a href="?demo_type=woocommerce" class="btn bg-woocommerce btn-app demo-login" data-toggle="tooltip"
                        title="WooCommerce demo user - Open web shop in minutes!!" style="color:white !important"
                        data-admin="{{ $demo_types['woocommerce'] }}"> <i class="fab fa-wordpress"></i> WooCommerce</a>

                    <a href="?demo_type=essentials" class="btn bg-navy btn-app demo-login" data-toggle="tooltip"
                        title="Essentials & HRM (human resource management) Module Demo" style="color:white !important"
                        data-admin="{{ $demo_types['essentials'] }}">
                        <i class="fas fa-check-circle"></i>
                        Essentials & HRM</a>

                    <a href="?demo_type=manufacturing" class="btn bg-orange btn-app demo-login" data-toggle="tooltip"
                        title="Manufacturing module demo" style="color:white !important"
                        data-admin="{{ $demo_types['manufacturing'] }}">
                        <i class="fas fa-industry"></i>
                        Manufacturing Module</a>

                    <a href="?demo_type=superadmin" class="btn bg-maroon btn-app demo-login" data-toggle="tooltip"
                        title="Project module demo" style="color:white !important"
                        data-admin="{{ $demo_types['superadmin'] }}">
                        <i class="fas fa-project-diagram"></i>
                        Project Module</a>

                    <a href="?demo_type=services" class="btn btn-app demo-login" data-toggle="tooltip"
                        title="Advance repair module demo" style="color:white !important; background-color: #bc8f8f"
                        data-admin="{{ $demo_types['services'] }}">
                        <i class="fas fa-wrench"></i>
                        Advance Repair Module</a>

                    <a href="{{ url('docs') }}" target="_blank" class="btn btn-app" data-toggle="tooltip"
                        title="Advance repair module demo" style="color:white !important; background-color: #2dce89">
                        <i class="fas fa-network-wired"></i>
                        Connector Module / API Documentation</a>
                @endcomponent
            
            
        
    @endif
        </div>
        {{-- centered login card --}}
        <div style="width:100%; max-width:440px; margin:0 auto;">

            {{-- App Branding --}}
            <div style="display:flex; flex-direction:column; align-items:center; margin-bottom:28px;">
                <div style="display:flex; align-items:center; justify-content:center; width:56px; height:56px; background:linear-gradient(135deg,#4f46e5,#6366f1); border-radius:16px; box-shadow:0 8px 24px rgba(79,70,229,0.35); margin-bottom:16px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </div>
                <h1 style="font-size:1.5rem; font-weight:800; color:#ffffff; margin:0 0 4px; letter-spacing:-0.02em; text-align:center;">
                    {{ config('app.name', 'FastPos ERP') }}
                </h1>
                <p style="color:rgba(148,163,184,0.9); font-size:0.875rem; font-weight:400; margin:0; text-align:center;">
                    @lang('lang_v1.welcome_back') — @lang('lang_v1.login_to_your') account
                </p>
            </div>

            {{-- Login Card --}}
            <div style="background:rgba(255,255,255,0.97); border-radius:20px; padding:36px 32px; box-shadow:0 25px 60px rgba(0,0,0,0.35), 0 8px 20px rgba(0,0,0,0.15); border:1px solid rgba(255,255,255,0.2);">

                @if ($errors->any())
                    <div style="background:#fff1f2; border:1px solid #fda4af; border-radius:10px; padding:12px 14px; margin-bottom:20px; color:#881337; font-size:0.8125rem; font-weight:500;">
                        <strong>@lang('lang_v1.login_failed')</strong>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" id="login-form">
                    {{ csrf_field() }}

                    {{-- Username --}}
                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-size:0.8125rem; font-weight:600; color:#1e293b; margin-bottom:6px;">
                            @lang('lang_v1.username')
                        </label>
                        <input
                            id="username"
                            type="text"
                            name="username"
                            value="{{ $username ?? old('username') }}"
                            required autofocus
                            placeholder="@lang('lang_v1.username')"
                            data-last-active-input=""
                            style="width:100%; height:46px; border-radius:10px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#0f172a; font-size:0.9rem; font-weight:500; padding:0 14px; outline:none; transition:all 0.2s; box-sizing:border-box;"
                            onfocus="this.style.borderColor='#4f46e5'; this.style.boxShadow='0 0 0 3px rgba(79,70,229,0.18)'; this.style.background='#fff';"
                            onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'; this.style.background='#f8fafc';"
                        />
                        @if ($errors->has('username'))
                            <span style="color:#ef4444; font-size:0.75rem; font-weight:500; margin-top:4px; display:block;">{{ $errors->first('username') }}</span>
                        @endif
                    </div>

                    {{-- Password --}}
                    <div style="margin-bottom:20px;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                            <label style="font-size:0.8125rem; font-weight:600; color:#1e293b; margin:0;">
                                @lang('lang_v1.password')
                            </label>
                            @if (config('app.env') != 'demo')
                                <a href="{{ route('password.request') }}"
                                    style="font-size:0.75rem; font-weight:600; color:#4f46e5; text-decoration:none;"
                                    tabindex="-1">@lang('lang_v1.forgot_your_password')</a>
                            @endif
                        </div>
                        <div style="position:relative;">
                            <input
                                id="password"
                                type="password"
                                name="password"
                                value="{{ $password ?? '' }}"
                                required
                                placeholder="@lang('lang_v1.password')"
                                style="width:100%; height:46px; border-radius:10px; border:1.5px solid #e2e8f0; background:#f8fafc; color:#0f172a; font-size:0.9rem; font-weight:500; padding:0 44px 0 14px; outline:none; transition:all 0.2s; box-sizing:border-box;"
                                onfocus="this.style.borderColor='#4f46e5'; this.style.boxShadow='0 0 0 3px rgba(79,70,229,0.18)'; this.style.background='#fff';"
                                onblur="this.style.borderColor='#e2e8f0'; this.style.boxShadow='none'; this.style.background='#f8fafc';"
                            />
                            <button type="button" id="show_hide_icon" class="show_hide_icon"
                                style="position:absolute; top:50%; right:12px; transform:translateY(-50%); background:none; border:none; cursor:pointer; padding:0; display:flex; align-items:center; color:#94a3b8;">
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye" width="20" height="20" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                    <path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" />
                                    <path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6" />
                                </svg>
                            </button>
                        </div>
                        @if ($errors->has('password'))
                            <span style="color:#ef4444; font-size:0.75rem; font-weight:500; margin-top:4px; display:block;">{{ $errors->first('password') }}</span>
                        @endif
                    </div>

                    {{-- Remember Me --}}
                    <div style="display:flex; align-items:center; gap:8px; margin-bottom:22px;">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}
                            style="width:16px; height:16px; border-radius:4px; accent-color:#4f46e5; cursor:pointer;">
                        <label for="remember" style="font-size:0.8125rem; font-weight:500; color:#475569; cursor:pointer; margin:0;">
                            @lang('lang_v1.remember_me')
                        </label>
                    </div>

                    @if(config('constants.enable_recaptcha'))
                    <div style="margin-bottom:20px;">
                        <div class="g-recaptcha" data-sitekey="{{ config('constants.google_recaptcha_key') }}"></div>
                        @if ($errors->has('g-recaptcha-response'))
                            <span style="color:#ef4444; font-size:0.75rem;">{{ $errors->first('g-recaptcha-response') }}</span>
                        @endif
                    </div>
                    @endif

                    {{-- Submit --}}
                    <button type="submit"
                        style="width:100%; height:48px; background:linear-gradient(135deg,#4f46e5 0%,#6366f1 100%); color:#ffffff; font-size:0.9375rem; font-weight:700; border:none; border-radius:10px; cursor:pointer; box-shadow:0 4px 14px rgba(79,70,229,0.35); transition:all 0.22s; letter-spacing:0.01em;">
                        @lang('lang_v1.login')
                    </button>
                </form>

                @if (!($request->segment(1) == 'business' && $request->segment(2) == 'register'))
                    @if (config('constants.allow_registration'))
                        <div style="text-align:center; margin-top:22px; padding-top:22px; border-top:1px solid #f1f5f9;">
                            <span style="font-size:0.8125rem; color:#64748b;">{{ __('business.not_yet_registered') }}</span>
                            <a href="{{ route('business.getRegister') }}@if(!empty(request()->lang)){{'?lang='.request()->lang}}@endif"
                                style="font-size:0.8125rem; font-weight:700; color:#4f46e5; text-decoration:none; margin-left:4px;">
                                {{ __('business.register_now') }}
                            </a>
                        </div>
                    @endif
                @endif
            </div>

            {{-- Footer note --}}
            <p style="text-align:center; color:rgba(148,163,184,0.7); font-size:0.75rem; margin-top:24px; font-weight:400;">
                © {{ date('Y') }} {{ config('app.name', 'FastPos ERP') }} &mdash; Enterprise Point of Sale
            </p>
        </div>

@stop
@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('#show_hide_icon').off('click');
            $('.change_lang').click(function() {
                window.location = "{{ route('login') }}?lang=" + $(this).attr('value');
            });
            $('a.demo-login').click(function(e) {
                e.preventDefault();
                $('#username').val($(this).data('admin'));
                $('#password').val("{{ $password }}");
                $('form#login-form').submit();
            });

            $('#show_hide_icon').on('click', function(e) {
            e.preventDefault();
            const passwordInput = $('#password');

            if (passwordInput.attr('type') === 'password') {
                passwordInput.attr('type', 'text');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye-off tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.585 10.587a2 2 0 0 0 2.829 2.828"/><path d="M16.681 16.673a8.717 8.717 0 0 1 -4.681 1.327c-3.6 0 -6.6 -2 -9 -6c1.272 -2.12 2.712 -3.678 4.32 -4.674m2.86 -1.146a9.055 9.055 0 0 1 1.82 -.18c3.6 0 6.6 2 9 6c-.666 1.11 -1.379 2.067 -2.138 2.87"/><path d="M3 3l18 18"/></svg>');
            }
            else if (passwordInput.attr('type') === 'text') {
                passwordInput.attr('type', 'password');
                $('#show_hide_icon').html('<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-eye tw-w-6" viewBox="0 0 24 24" stroke-width="1.5" stroke="#000000" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10 12a2 2 0 1 0 4 0a2 2 0 0 0 -4 0"/><path d="M21 12c-2.4 4 -5.4 6 -9 6c-3.6 0 -6.6 -2 -9 -6c2.4 -4 5.4 -6 9 -6c3.6 0 6.6 2 9 6"/></svg>');
            }
        });
        })
    </script>
@endsection
