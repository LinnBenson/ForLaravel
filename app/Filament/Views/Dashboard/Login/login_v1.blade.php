<x-filament-panels::page.simple>

    <link rel="stylesheet" href="{{ asset( 'filament/css/pages/login-v1.css' ) }}?v={{ filemtime( public_path( 'filament/css/pages/login-v1.css' ) ) }}">

    {{-- 管理员登录页面 --}}
    <main class="admin-login">
        <section class="admin-login-visual" aria-label="后台管理系统介绍">
            <div class="admin-login-grid"></div>
            <div class="admin-login-orb admin-login-orb-top"></div>
            <div class="admin-login-orb admin-login-orb-bottom"></div>

            <div class="admin-login-visual-content">
                <header class="admin-login-brand">
                    <span class="admin-login-logo">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" />
                            <path d="M8.5 12.2 11 14.7l4.8-5.2" />
                        </svg>
                    </span>
                    <span>{{ setting( 'app.title' ) }}</span>
                </header>

                <div class="admin-login-intro">
                    <span class="admin-login-eyebrow">ADMINISTRATION</span>
                    <h1>让管理工作<br>清晰而高效</h1>
                    <p>统一、安全的后台管理中心，帮助你专注于每一项重要工作。</p>
                </div>

                <footer class="admin-login-status">
                    <span><i></i> 系统服务正常</span>
                    <span>Secure Console</span>
                </footer>
            </div>
        </section>

        <section class="admin-login-panel">
            <div class="admin-login-form">
                <div class="admin-login-mobile-brand">
                    <span class="admin-login-logo">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Z" />
                            <path d="M8.5 12.2 11 14.7l4.8-5.2" />
                        </svg>
                    </span>
                    <strong>{{ setting( 'app.title' ) }}</strong>
                </div>

                <header class="admin-login-heading">
                    <span>欢迎回来</span>
                    <h2>登录管理后台</h2>
                    <p>请输入管理员账号和密码继续访问</p>
                </header>

                {{ $this->content }}

                <div class="admin-login-safe">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M12 3 5 6v5c0 4.7 2.9 8.1 7 10 4.1-1.9 7-5.3 7-10V6l-7-3Z" />
                        <path d="m9.5 12 1.7 1.7 3.5-4" />
                    </svg>
                    <span>你的登录信息已加密传输</span>
                </div>
            </div>

            <p class="admin-login-copyright">© {{ date( 'Y' ) }} {{ setting( 'app.title' ) }}</p>
        </section>
    </main>
</x-filament-panels::page.simple>
