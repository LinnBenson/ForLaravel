@php
    $installed = \App\Plugins\PluginManage\Models\Packages::schema()->hasTable( 'packages' );
@endphp

{{-- 插件包数据表管理模块 --}}
<div
    class="plugin-manage-database"
    x-data="{
        running: false,
        installed: @js( $installed ),
        message: '',
        messageStatus: '',
        async rebuild() {
            if ( this.running ) { return; }
            if ( !confirm( '确定重装插件包数据表吗？\n\n现有插件包数据将被永久删除，此操作无法撤销。' ) ) { return; }
            this.running = true;
            this.message = '';
            this.messageStatus = '';
            try {
                const response = await fetch( `{{ url( config( 'app.admin_path' ).'/plugins/plugin-manage/rebuild-tables' ) }}`, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.$refs.token.value,
                    },
                } );
                const data = await response.json();
                const succeeded = response.ok && data.status === 'success';
                this.installed = succeeded ? true : this.installed;
                this.messageStatus = succeeded ? 'success' : 'error';
                this.message = data.data?.message || data.message || '操作已完成。';
            }catch ( error ) {
                this.messageStatus = 'error';
                this.message = '请求失败，请稍后重试。';
            }finally {
                this.running = false;
            }
        },
    }"
>
    <input x-ref="token" type="hidden" value="{{ csrf_token() }}">

    <section class="plugin-manage-database-card">
        <div class="plugin-manage-database-content">
            <div class="plugin-manage-database-heading">
                <div>
                    <span>数据存储</span>
                    <h2>插件包数据表</h2>
                </div>
                <span class="plugin-manage-database-status" x-bind:data-installed="installed">
                    <i></i>
                    <span x-text="installed ? '已安装' : '未安装'">{{ $installed ? '已安装' : '未安装' }}</span>
                </span>
            </div>
            <p>用于保存插件名称、版本、作者及下载次数。重装会永久删除所有已有插件包数据。</p>
            <button type="button" x-on:click="rebuild()" x-bind:disabled="running">
                <span x-show="running" class="plugin-manage-database-spinner"></span>
                <span x-text="running ? '重装中…' : '重装数据表'">重装数据表</span>
            </button>
        </div>
    </section>

    <div
        class="plugin-manage-database-message"
        x-cloak
        x-show="message !== ''"
        x-bind:data-status="messageStatus"
        x-text="message"
    ></div>
</div>

<style>
    /* 插件包数据表管理模块 */
    .plugin-manage-database { display: flex; flex-direction: column; color: var(--gray-700); }
    .dark .plugin-manage-database { color: var(--gray-300); }
    .plugin-manage-database-card { padding: 1.25rem; border: 1px solid var(--gray-200); border-radius: 1rem; background: white; }
    .dark .plugin-manage-database-card { border-color: var(--gray-700); background: var(--gray-900); }
    .plugin-manage-database-content { min-width: 0; }
    .plugin-manage-database-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
    .plugin-manage-database-heading > div > span { color: var(--primary-600); font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em; }
    .plugin-manage-database h2 { margin-top: 0.15rem; color: var(--gray-950); font-size: 1.15rem; font-weight: 700; }
    .dark .plugin-manage-database h2 { color: white; }
    .plugin-manage-database-content > p { margin: 0.55rem 0 1rem; color: var(--gray-600); font-size: 0.85rem; }
    .dark .plugin-manage-database-content > p { color: var(--gray-400); }
    .plugin-manage-database-status { display: inline-flex; padding: 0.3rem 0.6rem; border-radius: 9999px; background: var(--gray-100); color: var(--gray-600); align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 600; }
    .dark .plugin-manage-database-status { background: var(--gray-800); color: var(--gray-300); }
    .plugin-manage-database-status i { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--gray-400); }
    .plugin-manage-database-status[data-installed="true"] { background: color-mix(in srgb, var(--success-500) 12%, transparent); color: var(--success-700); }
    .plugin-manage-database-status[data-installed="true"] i { background: var(--success-500); }
    .plugin-manage-database button { display: inline-flex; min-height: 2.35rem; padding: 0.55rem 0.9rem; border: 0; border-radius: 0.6rem; background: var(--danger-600); color: white; align-items: center; justify-content: center; gap: 0.45rem; font-size: 0.82rem; font-weight: 650; cursor: pointer; }
    .plugin-manage-database button:disabled { cursor: wait; opacity: 0.65; }
    .plugin-manage-database-spinner { width: 0.9rem; height: 0.9rem; border: 2px solid rgba(255, 255, 255, 0.4); border-top-color: white; border-radius: 50%; animation: plugin-manage-spin 0.7s linear infinite; }
    .plugin-manage-database-message { margin-top: 0.75rem; padding: 0.7rem 0.85rem; border-radius: 0.6rem; font-size: 0.82rem; }
    .plugin-manage-database-message[data-status="success"] { background: color-mix(in srgb, var(--success-500) 12%, transparent); color: var(--success-700); }
    .plugin-manage-database-message[data-status="error"] { background: color-mix(in srgb, var(--danger-500) 12%, transparent); color: var(--danger-700); }
    @keyframes plugin-manage-spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
        .plugin-manage-database-card { padding: 1rem; }
        .plugin-manage-database button { width: 100%; }
    }
</style>
