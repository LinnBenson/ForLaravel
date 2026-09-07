<x-filament-panels::page.simple>
    @php
        $brand = setting( 'app.title' ) ?: filament()->getBrandName();
    @endphp

    <link rel="stylesheet" href="{{ asset( 'filament/css/pages/login-v2.css' ) }}?v={{ filemtime( public_path( 'filament/css/pages/login-v2.css' ) ) }}">

    {{-- V2 登录页：品牌导航、建筑线条背景和独立认证卡片 --}}
    <div class="login-v2">
        <header class="login-v2-topbar">
            <div class="login-v2-brand">
                <span class="login-v2-symbol" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>{{ $brand }}</span>
            </div>
            @if ( filament()->hasDarkMode() && !filament()->hasDarkModeForced() )
                <div class="login-v2-theme" x-data="{ close() {} }">
                    <x-filament-panels::theme-switcher />
                </div>
            @endif
        </header>

        <div class="login-v2-stage">
            {{-- 纯装饰背景，不参与交互或屏幕阅读 --}}
            <div class="login-v2-architecture" aria-hidden="true">
                <div class="login-v2-arch login-v2-arch-outer"></div>
                <div class="login-v2-arch login-v2-arch-middle"></div>
                <div class="login-v2-arch login-v2-arch-inner"></div>
                <span class="login-v2-coordinate login-v2-coordinate-left">01 / 工作空间</span>
                <span class="login-v2-coordinate login-v2-coordinate-right">专注于重要的事</span>
                <div class="login-v2-baseline"></div>
            </div>

            <section class="login-v2-entry" aria-labelledby="login-v2-title">
                <header class="login-v2-intro">
                    <span class="login-v2-eyebrow"><i aria-hidden="true"></i> 焕新你的工作空间</span>
                    <h1 id="login-v2-title">专注，从这里开始。</h1>
                    <p>回到你的工作空间，让每一项管理井然有序。</p>
                </header>

                <div class="login-v2-card">
                    <header class="login-v2-card-heading">
                        <div>
                            <span class="login-v2-kicker">欢迎回来</span>
                            <h2>登录管理后台</h2>
                        </div>
                        <span class="login-v2-key" aria-hidden="true">
                            <x-filament::icon icon="heroicon-o-finger-print" />
                        </span>
                    </header>

                    {{-- 保留 Filament 的表单、校验、提交状态及多因素认证流程 --}}
                    {{ $this->content }}

                    <p class="login-v2-note">
                        <x-filament::icon icon="heroicon-o-lock-closed" />
                        <span>仅限授权管理员访问</span>
                    </p>
                </div>

                <p class="login-v2-caption"><span aria-hidden="true"></span>留一点空间，给重要的事。<span aria-hidden="true"></span></p>
            </section>
        </div>

        <footer class="login-v2-footer">
            <span>© {{ date( 'Y' ) }} {{ $brand }}</span>
            <span>管理有序 · 工作从容</span>
        </footer>
    </div>
</x-filament-panels::page.simple>
