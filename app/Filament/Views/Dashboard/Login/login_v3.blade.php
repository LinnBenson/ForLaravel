<x-filament-panels::page.simple>
    @php
        $brand = setting( 'app.title' ) ?: filament()->getBrandName();
    @endphp

    <link rel="stylesheet" href="{{ asset( 'filament/css/pages/login-v3.css' ) }}?v={{ filemtime( public_path( 'filament/css/pages/login-v3.css' ) ) }}">

    {{-- V3 登录页：山湖背景与毛玻璃认证卡片 --}}
    <div class="login-v3">
        <header class="login-v3-topbar">
            <div class="login-v3-brand">
                <span class="login-v3-symbol" aria-hidden="true">
                    <x-filament::icon icon="heroicon-o-squares-2x2" />
                </span>
                <span>{{ $brand }}</span>
            </div>
            @if ( filament()->hasDarkMode() && !filament()->hasDarkModeForced() )
                <div class="login-v3-theme" x-data="{ close() {} }">
                    <x-filament-panels::theme-switcher />
                </div>
            @endif
        </header>

        <div class="login-v3-stage">
            <section class="login-v3-intro" aria-labelledby="login-v3-intro-title">
                <span class="login-v3-eyebrow"><span aria-hidden="true"></span> 你的工作空间</span>
                <h1 id="login-v3-intro-title">心有从容，<br>工作自有章法。</h1>
                <p>从这里开始，专注于每一件重要的事。</p>
            </section>

            <section class="login-v3-card" aria-labelledby="login-v3-title">
                <header class="login-v3-card-heading">
                    <span class="login-v3-key" aria-hidden="true">
                        <x-filament::icon icon="heroicon-o-finger-print" />
                    </span>
                    <p class="login-v3-welcome">欢迎回来</p>
                    <h2 id="login-v3-title">登录管理后台</h2>
                    <p class="login-v3-description">请输入管理员账号和密码继续访问</p>
                </header>

                {{-- 沿用 Filament 的表单、校验、提交状态及多因素认证流程 --}}
                {{ $this->content }}

                <p class="login-v3-note">
                    <x-filament::icon icon="heroicon-o-lock-closed" />
                    <span>仅限授权管理员访问</span>
                </p>
            </section>
        </div>

        <footer class="login-v3-footer">
            <span>© {{ date( 'Y' ) }} {{ $brand }}</span>
            <span>管理有序 · 工作从容</span>
        </footer>
    </div>
</x-filament-panels::page.simple>
