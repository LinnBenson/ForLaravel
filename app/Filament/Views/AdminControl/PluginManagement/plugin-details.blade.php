{{-- 插件详细信息 --}}
<div class="plugin-details">
    <div class="plugin-details-summary">
        <span class="plugin-details-icon">
            <x-filament::icon :icon="$plugin['type'] === 'plugin' ? 'heroicon-o-puzzle-piece' : 'heroicon-o-cube'" />
        </span>
        <div>
            <strong>{{ $plugin['name'] }}</strong>
            <p>{{ $plugin['description'] }}</p>
        </div>
    </div>

    @if ( $plugin['has_hooks'] || $plugin['has_config'] || $plugin['has_admin'] || $plugin['has_source'] || \App\Filament\Resources\AdminControl\PluginManagement\Services\PluginPublisher::isConfigured() )
        <div class="plugin-details-actions">
            @if ( $plugin['has_hooks'] )
                @if ( $plugin['hooks_trusted'] )
                    <x-filament::button
                        size="sm"
                        color="danger"
                        icon="heroicon-o-shield-check"
                        wire:click="switchPluginDetailsAction('cancelHooks', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                    >
                        取消 Hook
                    </x-filament::button>
                @else
                    <x-filament::button
                        size="sm"
                        color="warning"
                        icon="heroicon-o-shield-exclamation"
                        wire:click="switchPluginDetailsAction('trustHooks', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                    >
                        信任 Hook
                    </x-filament::button>
                @endif
            @endif
            @if ( $plugin['has_config'] )
                <x-filament::button
                    size="sm"
                    color="gray"
                    icon="heroicon-o-cog-6-tooth"
                    wire:click="switchPluginDetailsAction('editConfig', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                >
                    修改配置
                </x-filament::button>
            @endif
            @if ( $plugin['has_admin'] )
                <x-filament::button
                    size="sm"
                    color="primary"
                    icon="heroicon-o-wrench-screwdriver"
                    wire:click="switchPluginDetailsAction('managePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                >
                    管理插件
                </x-filament::button>
            @endif
            @if ( $plugin['has_source'] )
                <x-filament::button
                    size="sm"
                    color="primary"
                    icon="heroicon-o-arrow-path"
                    wire:click="switchPluginDetailsAction('updatePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                >
                    更新插件
                </x-filament::button>
            @endif
            @if ( \App\Filament\Resources\AdminControl\PluginManagement\Services\PluginPublisher::isConfigured() )
                <x-filament::button
                    size="sm"
                    color="primary"
                    icon="heroicon-o-cloud-arrow-up"
                    wire:click="switchPluginDetailsAction('publishPlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                >
                    上传插件
                </x-filament::button>
            @endif
        </div>
    @endif

    <dl class="plugin-details-meta">
        <div><dt>插件标识</dt><dd>{{ $plugin['id'] }}</dd></div>
        <div><dt>插件类型</dt><dd>{{ $plugin['type'] === 'plugin' ? '功能插件' : '依赖插件' }}</dd></div>
        <div><dt>版本</dt><dd>{{ $plugin['version'] }}</dd></div>
        <div><dt>作者</dt><dd>{{ $plugin['author'] }}</dd></div>
        <div><dt>插件配置</dt><dd>{{ $plugin['has_config'] ? '存在可配置项' : '无配置项' }}</dd></div>
    </dl>

    <div class="plugin-details-sections">
        <section>
            <h3>Composer 依赖</h3>
            @forelse ( $plugin['composer_dependencies'] as $package => $constraint )
                <div class="plugin-details-row"><code>{{ $package }}</code><span>{{ $constraint }}</span></div>
            @empty
                <p class="plugin-details-empty">无 Composer 依赖</p>
            @endforelse
        </section>
        <section>
            <h3>插件依赖</h3>
            @forelse ( $plugin['plugin_dependencies'] as $pluginId => $constraint )
                <div class="plugin-details-row"><code>{{ $pluginId }}</code><span>{{ $constraint }}</span></div>
            @empty
                <p class="plugin-details-empty">无插件依赖</p>
            @endforelse
        </section>
    </div>

    <section class="plugin-details-hooks">
        <h3>申请的 Hook</h3>
        @forelse ( $plugin['hooks'] as $hook => $description )
            <div class="plugin-details-hook">
                <code>{{ $hook }}</code>
                <span>{{ $description }}</span>
            </div>
        @empty
            <p class="plugin-details-empty">未申请 Hook</p>
        @endforelse
    </section>

    @if ( $plugin['readme'] !== null )
        <section class="plugin-details-readme">
            <h3>README.md</h3>
            <article>{!! $plugin['readme'] !!}</article>
        </section>
    @endif
</div>

<style>
    /* 插件详情概要 */
    .plugin-details-modal {
        max-height: calc(100dvh - 2rem);
        overflow: hidden;
    }
    .plugin-details-modal .fi-modal-content {
        flex: 1 1 auto;
        min-width: 0;
        min-height: 0;
        overflow-x: hidden;
        overflow-y: auto;
        overscroll-behavior: contain;
        -webkit-overflow-scrolling: touch;
    }
    .plugin-details {
        display: flex;
        width: 100%;
        min-width: 0;
        flex-direction: column;
        gap: 1rem;
    }
    .plugin-details-summary {
        display: flex;
        min-width: 0;
        padding: 1rem;
        border-radius: 0.75rem;
        background: color-mix(in srgb, var(--primary-500) 8%, transparent);
        align-items: center;
        gap: 0.875rem;
    }
    .plugin-details-icon {
        display: inline-flex;
        width: 3rem;
        height: 3rem;
        border-radius: 0.75rem;
        background: color-mix(in srgb, var(--primary-500) 14%, transparent);
        color: var(--primary-600);
        align-items: center;
        justify-content: center;
        flex: 0 0 auto;
    }
    .plugin-details-icon svg {
        width: 1.5rem;
        height: 1.5rem;
    }
    .plugin-details-summary strong {
        font-size: 1rem;
    }
    .plugin-details-summary > div {
        min-width: 0;
    }
    .plugin-details-summary p {
        margin-top: 0.25rem;
        color: #6b7280;
        font-size: 0.8125rem;
    }
    /* 插件详情操作 */
    .plugin-details-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.625rem;
    }
    /* 插件元数据 */
    .plugin-details-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem;
    }
    .plugin-details-meta div {
        min-width: 0;
        padding: 0.75rem;
        border: 1px solid rgba(107, 114, 128, 0.16);
        border-radius: 0.625rem;
    }
    .plugin-details-meta dt {
        color: #9ca3af;
        font-size: 0.6875rem;
    }
    .plugin-details-meta dd {
        overflow: hidden;
        margin-top: 0.2rem;
        font-size: 0.8125rem;
        font-weight: 600;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    /* 插件依赖与 Hook 列表 */
    .plugin-details-sections {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
    }
    .plugin-details-sections section,
    .plugin-details-hooks,
    .plugin-details-readme {
        padding: 1rem;
        border: 1px solid rgba(107, 114, 128, 0.16);
        border-radius: 0.75rem;
    }
    .plugin-details-sections h3,
    .plugin-details-hooks h3,
    .plugin-details-readme > h3 {
        margin-bottom: 0.75rem;
        font-size: 0.875rem;
        font-weight: 700;
    }
    .plugin-details-row,
    .plugin-details-hook {
        display: flex;
        padding: 0.625rem 0;
        border-top: 1px solid rgba(107, 114, 128, 0.12);
        justify-content: space-between;
        gap: 0.75rem;
    }
    .plugin-details-row:first-of-type,
    .plugin-details-hook:first-of-type {
        padding-top: 0;
        border-top: 0;
    }
    .plugin-details-row code,
    .plugin-details-hook code {
        overflow-wrap: anywhere;
        font-size: 0.75rem;
    }
    .plugin-details-row span,
    .plugin-details-hook span {
        color: #6b7280;
        font-size: 0.75rem;
        text-align: right;
    }
    .plugin-details-hook {
        align-items: flex-start;
        flex-direction: column;
    }
    .plugin-details-hook span {
        text-align: left;
    }
    .plugin-details-empty {
        color: #9ca3af;
        font-size: 0.75rem;
    }
    /* 插件 README Markdown 内容 */
    .plugin-details-readme article {
        overflow-wrap: anywhere;
        color: inherit;
        font-size: 0.8125rem;
        line-height: 1.7;
    }
    .plugin-details-readme article h1,
    .plugin-details-readme article h2,
    .plugin-details-readme article h3,
    .plugin-details-readme article h4 {
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
        font-weight: 700;
        line-height: 1.35;
    }
    .plugin-details-readme article h1:first-child,
    .plugin-details-readme article h2:first-child,
    .plugin-details-readme article h3:first-child {
        margin-top: 0;
    }
    .plugin-details-readme article h1 {
        font-size: 1.25rem;
    }
    .plugin-details-readme article h2 {
        padding-bottom: 0.375rem;
        border-bottom: 1px solid rgba(107, 114, 128, 0.16);
        font-size: 1.0625rem;
    }
    .plugin-details-readme article h3,
    .plugin-details-readme article h4 {
        font-size: 0.9375rem;
    }
    .plugin-details-readme article p,
    .plugin-details-readme article ul,
    .plugin-details-readme article ol,
    .plugin-details-readme article blockquote,
    .plugin-details-readme article pre,
    .plugin-details-readme article table {
        margin: 0.625rem 0;
    }
    .plugin-details-readme article ul,
    .plugin-details-readme article ol {
        padding-left: 1.5rem;
    }
    .plugin-details-readme article ul {
        list-style: disc;
    }
    .plugin-details-readme article ol {
        list-style: decimal;
    }
    .plugin-details-readme article a {
        color: var(--primary-600);
        text-decoration: underline;
        text-underline-offset: 0.15rem;
    }
    .plugin-details-readme article code {
        padding: 0.125rem 0.3rem;
        border-radius: 0.25rem;
        background: rgba(107, 114, 128, 0.12);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 0.875em;
    }
    .plugin-details-readme article pre {
        padding: 0.875rem;
        overflow-x: auto;
        border-radius: 0.5rem;
        background: #111827;
        color: #e5e7eb;
    }
    .plugin-details-readme article pre code {
        padding: 0;
        background: transparent;
        color: inherit;
    }
    .plugin-details-readme article blockquote {
        padding: 0.5rem 0.75rem;
        border-left: 0.2rem solid var(--primary-500);
        background: rgba(107, 114, 128, 0.06);
        color: #6b7280;
    }
    .plugin-details-readme article table {
        width: 100%;
        border-collapse: collapse;
    }
    .plugin-details-readme article th,
    .plugin-details-readme article td {
        padding: 0.5rem;
        border: 1px solid rgba(107, 114, 128, 0.2);
        text-align: left;
    }
    @media (max-width: 640px) {
        .plugin-details-modal {
            max-height: calc(100dvh - 1rem);
        }
        .plugin-details-modal .fi-modal-header {
            padding-inline: 1rem;
            padding-top: 1rem;
        }
        .plugin-details-modal .fi-modal-content,
        .plugin-details-modal .fi-modal-footer {
            padding-inline: 1rem;
        }
        .plugin-details {
            padding-right: 0;
        }
        .plugin-details-summary {
            padding: 0.875rem;
            align-items: flex-start;
        }
        .plugin-details-meta,
        .plugin-details-sections {
            grid-template-columns: minmax(0, 1fr);
        }
    }
</style>
