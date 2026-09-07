<x-filament-panels::page.simple>
    @php
        $brand = setting( 'app.title' ) ?: filament()->getBrandName();
    @endphp

    <link rel="stylesheet" href="{{ asset( 'filament/css/pages/login-v0.css' ) }}?v={{ filemtime( public_path( 'filament/css/pages/login-v0.css' ) ) }}">

    {{-- V4 登录页：斜切湖畔照片与双栏认证面板 --}}
    <div class="login-v0">
        <div class="login-v0-backdrop" aria-hidden="true"></div>
        <div class="login-v0-panel">
            <aside class="login-v0-visual" aria-label="品牌介绍">
                <div class="login-v0-photo" aria-hidden="true"></div>
                <div class="login-v0-brand">
                    <x-filament::icon icon="heroicon-o-squares-2x2" />
                    <span>{{ $brand }}</span>
                </div>
                <div class="login-v0-caption">
                    <span class="login-v0-caption-line" aria-hidden="true"></span>
                    <p>让每一份专注，<br>都有所成。</p>
                    <span>你的工作空间，尽在掌握。</span>
                </div>
            </aside>

            <section class="login-v0-entry" aria-labelledby="login-v0-title">
                <div class="login-v0-toolbar">
                    <span class="login-v0-mobile-brand">{{ $brand }}</span>
                    @if ( filament()->hasDarkMode() && !filament()->hasDarkModeForced() )
                        <div class="login-v0-theme" x-data="{ close() {} }">
                            <x-filament-panels::theme-switcher />
                        </div>
                    @endif
                </div>
                <div class="login-v0-form">
                    <header class="login-v0-heading">
                        <h1 id="login-v0-title">欢迎回来</h1>
                        <p>登录 {{ $brand }} 管理后台</p>
                    </header>

                    {{-- 沿用 Filament 的认证、表单校验与多因素认证 --}}
                    {{ $this->content }}

                    <p class="login-v0-note">
                        <x-filament::icon icon="heroicon-o-lock-closed" />
                        <span>仅限授权管理员访问</span>
                    </p>
                </div>
                <footer class="login-v0-footer">© {{ date( 'Y' ) }} {{ $brand }}</footer>
            </section>
        </div>
    </div>
</x-filament-panels::page.simple>
