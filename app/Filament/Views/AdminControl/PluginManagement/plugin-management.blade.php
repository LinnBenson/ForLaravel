<x-filament-panels::page>
    @php
        $plugins = $this->getPlugins();
        $featurePlugins = array_values( array_filter( $plugins, fn ( array $plugin ): bool => $plugin['type'] === 'plugin' ) );
        $relyPlugins = array_values( array_filter( $plugins, fn ( array $plugin ): bool => $plugin['type'] === 'rely' ) );
        $failedPlugins = array_values( array_filter( $plugins, fn ( array $plugin ): bool => $plugin['type'] === 'failed' ) );
    @endphp

    {{-- 插件分类管理区域 --}}
    <div class="plugin-management-layout">
        <section class="plugin-management-main">
            <!--
            <div class="plugin-management-heading">
                <div>
                    <h2>功能插件</h2>
                    <p>为系统提供独立功能的插件。</p>
                </div>
                <span>{{ count( $featurePlugins ) }} 个</span>
            </div>
            -->
            <div
                class="plugin-management-card-grid"
                x-data="{
                    dispose: null,
                    init() {
                        this.$nextTick(() => {
                            const grid = this.$el;
                            let masonry = null;
                            let frame = null;
                            let stopped = false;
                            let lastWidth = 0;
                            const observedCards = new Set();
                            const schedule = () => {
                                if ( stopped || frame !== null ) { return; }
                                frame = requestAnimationFrame(layout);
                            };
                            const resizeObserver = new ResizeObserver(entries => {
                                for ( const entry of entries ) {
                                    if ( entry.target !== grid || entry.contentRect.width !== lastWidth ) {
                                        if ( entry.target === grid ) { lastWidth = entry.contentRect.width; }
                                        schedule();
                                    }
                                }
                            });
                            // 卡片增删、按钮和文字更新后，重新收集卡片并排列。
                            const layout = () => {
                                frame = null;
                                if ( stopped || !grid.isConnected || !window.Masonry ) { return; }
                                const cards = [...grid.querySelectorAll(':scope > .plugin-management-card')];
                                for ( const card of observedCards ) {
                                    if ( !cards.includes(card) ) {
                                        resizeObserver.unobserve(card);
                                        observedCards.delete(card);
                                    }
                                }
                                if ( cards.length === 0 ) {
                                    masonry?.destroy();
                                    masonry = null;
                                    grid.classList.remove('is-masonry');
                                    return;
                                }
                                const gap = parseFloat(getComputedStyle(document.documentElement).fontSize);
                                const columns = Math.max(1, Math.floor((grid.clientWidth + gap) / (17 * gap + gap)));
                                const width = (grid.clientWidth - (columns - 1) * gap) / columns;
                                grid.classList.add('is-masonry');
                                for ( const card of cards ) {
                                    card.style.width = `${width}px`;
                                    if ( !observedCards.has(card) ) {
                                        resizeObserver.observe(card);
                                        observedCards.add(card);
                                    }
                                }
                                if ( !masonry ) {
                                    masonry = new window.Masonry(grid, {
                                        itemSelector: '.plugin-management-card',
                                        columnWidth: width,
                                        gutter: gap,
                                        percentPosition: true,
                                        transitionDuration: 0,
                                        resize: false,
                                        initLayout: false,
                                    });
                                }
                                masonry.options.columnWidth = width;
                                masonry.options.gutter = gap;
                                masonry.reloadItems();
                                masonry.layout();
                            };
                            const mutationObserver = new MutationObserver(schedule);
                            mutationObserver.observe(grid, { childList: true, subtree: true, characterData: true });
                            resizeObserver.observe(grid);
                            window.addEventListener('load', schedule, { once: true });
                            document.fonts.ready.then(schedule);
                            schedule();
                            // 离开页面时释放实例和监听，兼容后台 SPA 导航。
                            this.dispose = () => {
                                stopped = true;
                                cancelAnimationFrame(frame);
                                mutationObserver.disconnect();
                                resizeObserver.disconnect();
                                window.removeEventListener('load', schedule);
                                masonry?.destroy();
                            };
                        });
                    },
                    destroy() {
                        this.dispose?.();
                    },
                }"
            >
                @forelse ( $featurePlugins as $plugin )
                    <article class="plugin-management-card" wire:key="plugin-card-{{ $plugin['id'] }}">
                        <div class="plugin-management-card-top">
                            <span class="plugin-management-icon">
                                <x-filament::icon icon="heroicon-o-puzzle-piece" />
                            </span>
                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                :label="'删除 '.$plugin['name']"
                                wire:click="mountAction('deletePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                            />
                        </div>
                        <div
                            class="plugin-management-card-content"
                            role="button"
                            tabindex="0"
                            wire:click="mountAction('viewPluginDetails', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                            wire:keydown.enter="mountAction('viewPluginDetails', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                        >
                            <h3>{{ $plugin['name'] }}</h3>
                            <p>{{ $plugin['description'] }}</p>
                        </div>
                        <dl class="plugin-management-meta">
                            <div><dt>版本</dt><dd>{{ $plugin['version'] }}</dd></div>
                            <div><dt>作者</dt><dd>{{ $plugin['author'] }}</dd></div>
                            <div><dt>标识</dt><dd>{{ $plugin['id'] }}</dd></div>
                        </dl>
                        @if ( $plugin['has_hooks'] || $plugin['has_config'] || $plugin['has_admin'] )
                            <div class="plugin-management-card-actions">
                                @if ( $plugin['has_hooks'] )
                                    @if ( $plugin['hooks_trusted'] )
                                        <x-filament::button
                                            size="sm"
                                            color="danger"
                                            icon="heroicon-o-shield-check"
                                            wire:click="mountAction('cancelHooks', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                                        >
                                            取消 Hook
                                        </x-filament::button>
                                    @else
                                        <x-filament::button
                                            size="sm"
                                            color="warning"
                                            icon="heroicon-o-shield-exclamation"
                                            wire:click="mountAction('trustHooks', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
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
                                        wire:click="mountAction('editConfig', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                                    >
                                        修改配置
                                    </x-filament::button>
                                @endif
                                @if ( $plugin['has_admin'] )
                                    <x-filament::button
                                        size="sm"
                                        color="primary"
                                        icon="heroicon-o-wrench-screwdriver"
                                        wire:click="mountAction('managePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                                        style="width: 100%;"
                                    >
                                        管理插件
                                    </x-filament::button>
                                @endif
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="plugin-management-empty">
                        <x-filament::icon icon="heroicon-o-puzzle-piece" />
                        <strong>暂无功能插件</strong>
                        <span>类型为 plugin 的插件将显示在这里。</span>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="plugin-management-side">
            <div class="plugin-management-heading">
                <div>
                    <h2>依赖插件</h2>
                    <p>为系统或其他插件提供支持。</p>
                </div>
                <span>{{ count( $relyPlugins ) }} 个</span>
            </div>
            <div class="plugin-management-rely-list">
                @forelse ( $relyPlugins as $plugin )
                    <article class="plugin-management-rely-row" wire:key="plugin-rely-{{ $plugin['id'] }}">
                        <span
                            class="plugin-management-rely-select"
                            role="button"
                            tabindex="0"
                            wire:click="mountAction('viewPluginDetails', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                            wire:keydown.enter="mountAction('viewPluginDetails', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                        >
                            <span class="plugin-management-rely-icon">
                                <x-filament::icon icon="heroicon-o-cube" />
                            </span>
                            <span class="plugin-management-rely-content">
                                <strong>{{ $plugin['name'] }}</strong>
                                <small>{{ $plugin['id'] }} · {{ $plugin['version'] }}</small>
                            </span>
                        </span>
                        @if ( $plugin['has_hooks'] )
                            <x-filament::icon-button
                                :icon="$plugin['hooks_trusted'] ? 'heroicon-o-shield-check' : 'heroicon-o-shield-exclamation'"
                                :color="$plugin['hooks_trusted'] ? 'danger' : 'warning'"
                                :label="($plugin['hooks_trusted'] ? '取消 Hook：' : '信任 Hook：').$plugin['name']"
                                wire:click="mountAction('{{ $plugin['hooks_trusted'] ? 'cancelHooks' : 'trustHooks' }}', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                            />
                        @endif
                        <x-filament::icon-button
                            icon="heroicon-o-trash"
                            color="danger"
                            :label="'删除 '.$plugin['name']"
                            wire:click="mountAction('deletePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                        />
                    </article>
                @empty
                    <div class="plugin-management-empty is-compact">
                        <x-filament::icon icon="heroicon-o-cube" />
                        <strong>暂无依赖插件</strong>
                    </div>
                @endforelse
            </div>

            @if ( !empty( $failedPlugins ) )
                <div class="plugin-management-failed-heading">
                    <div>
                        <h2>加载失败</h2>
                        <p>未能正常初始化的插件。</p>
                    </div>
                    <span>{{ count( $failedPlugins ) }} 个</span>
                </div>
                <div class="plugin-management-failed-list">
                    @foreach ( $failedPlugins as $plugin )
                        <article class="plugin-management-failed-row" wire:key="plugin-failed-{{ $plugin['id'] }}">
                            <span class="plugin-management-failed-icon">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                            </span>
                            <span class="plugin-management-failed-content">
                                <strong>{{ $plugin['name'] }}</strong>
                                <small>{{ $plugin['description'] }}</small>
                            </span>
                            <x-filament::icon-button
                                icon="heroicon-o-trash"
                                color="danger"
                                :label="'删除 '.$plugin['name']"
                                wire:click="mountAction('deletePlugin', {{ \Illuminate\Support\Js::from(['pluginId' => $plugin['id']]) }})"
                            />
                        </article>
                    @endforeach
                </div>
            @endif
        </aside>
    </div>

    <style>
        /* 插件管理左右分栏布局 */
        .plugin-management-layout {
            display: grid;
            grid-template-columns: minmax(0, 3fr) minmax(18rem, 1fr);
            gap: 1.25rem;
            align-items: start;
        }
        .plugin-management-main,
        .plugin-management-side {
            min-width: 0;
        }
        .plugin-management-side {
            padding: 1.25rem;
            border: 1px solid rgba(107, 114, 128, 0.2);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.72);
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .dark .plugin-management-side {
            background: var(--gray-900);
        }
        .plugin-management-heading {
            display: flex;
            margin-bottom: 1.25rem;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }
        .plugin-management-heading h2 {
            font-size: 1.125rem;
            font-weight: 700;
        }
        .plugin-management-heading p {
            margin-top: 0.25rem;
            color: #6b7280;
            font-size: 0.8125rem;
        }
        .plugin-management-heading > span {
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background: color-mix(in srgb, var(--primary-500) 12%, transparent);
            color: var(--primary-600);
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        /* 功能插件卡片 */
        .plugin-management-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(min(100%, 17rem), 1fr));
            gap: 1rem;
            align-items: start;
        }
        .plugin-management-card-grid.is-masonry {
            display: block;
            position: relative;
        }
        .plugin-management-card-grid.is-masonry > .plugin-management-card {
            box-sizing: border-box;
            margin-bottom: 1rem;
        }
        .plugin-management-card {
            display: flex;
            padding: 1.125rem;
            border: 1px solid rgba(107, 114, 128, 0.2);
            border-radius: 0.75rem;
            background: rgba(255, 255, 255, 0.78);
            flex-direction: column;
            gap: 1rem;
        }
        .dark .plugin-management-card {
            background: var(--gray-900);
        }
        .plugin-management-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .plugin-management-icon,
        .plugin-management-rely-icon {
            display: inline-flex;
            border-radius: 0.625rem;
            background: color-mix(in srgb, var(--primary-500) 12%, transparent);
            color: var(--primary-600);
            align-items: center;
            justify-content: center;
        }
        .plugin-management-icon {
            width: 2.75rem;
            height: 2.75rem;
        }
        .plugin-management-icon svg {
            width: 1.375rem;
            height: 1.375rem;
        }
        .plugin-management-card-content {
            padding: 0.25rem;
            border-radius: 0.5rem;
            flex: 0 0 auto;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .plugin-management-card-content:hover,
        .plugin-management-card-content:focus-visible {
            background: color-mix(in srgb, var(--primary-500) 8%, transparent);
            outline: none;
        }
        .plugin-management-card-content h3 {
            font-size: 1rem;
            font-weight: 700;
        }
        .plugin-management-card-content p {
            display: -webkit-box;
            margin-top: 0.375rem;
            overflow: hidden;
            color: #6b7280;
            font-size: 0.8125rem;
            line-height: 1.6;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 3;
        }
        .plugin-management-meta {
            display: grid;
            padding-top: 0.875rem;
            border-top: 1px solid rgba(107, 114, 128, 0.16);
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.625rem 1rem;
        }
        .plugin-management-meta div:last-child {
            grid-column: 1 / -1;
        }
        .plugin-management-meta dt {
            color: #9ca3af;
            font-size: 0.6875rem;
        }
        .plugin-management-meta dd {
            overflow: hidden;
            margin-top: 0.125rem;
            font-size: 0.8125rem;
            font-weight: 600;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .plugin-management-card-actions {
            display: flex;
            padding-top: 0.875rem;
            border-top: 1px solid rgba(107, 114, 128, 0.16);
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        /* 依赖插件分行列表 */
        .plugin-management-rely-list {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .plugin-management-rely-row {
            display: flex;
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid rgba(107, 114, 128, 0.18);
            border-radius: 0.625rem;
            align-items: center;
            gap: 0.75rem;
        }
        .plugin-management-rely-icon {
            width: 2.25rem;
            height: 2.25rem;
            flex: 0 0 auto;
        }
        .plugin-management-rely-icon svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .plugin-management-rely-content {
            display: flex;
            min-width: 0;
            flex: 1;
            flex-direction: column;
        }
        .plugin-management-rely-select {
            display: flex;
            min-width: 0;
            border-radius: 0.5rem;
            align-items: center;
            flex: 1;
            gap: 0.75rem;
            cursor: pointer;
        }
        .plugin-management-rely-select:hover,
        .plugin-management-rely-select:focus-visible {
            background: color-mix(in srgb, var(--primary-500) 8%, transparent);
            outline: 0.25rem solid color-mix(in srgb, var(--primary-500) 8%, transparent);
        }
        .plugin-management-rely-content strong,
        .plugin-management-rely-content small {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .plugin-management-rely-content strong {
            font-size: 0.875rem;
        }
        .plugin-management-rely-content small {
            margin-top: 0.125rem;
            color: #6b7280;
            font-size: 0.6875rem;
        }
        /* 加载失败插件列表 */
        .plugin-management-failed-heading {
            display: flex;
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(107, 114, 128, 0.2);
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
        }
        .plugin-management-failed-heading h2 {
            color: #dc2626;
            font-size: 1rem;
            font-weight: 700;
        }
        .plugin-management-failed-heading p {
            margin-top: 0.25rem;
            color: #6b7280;
            font-size: 0.75rem;
        }
        .plugin-management-failed-heading > span {
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .plugin-management-failed-list {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }
        .plugin-management-failed-row {
            display: flex;
            min-width: 0;
            padding: 0.75rem;
            border: 1px solid rgba(239, 68, 68, 0.24);
            border-radius: 0.625rem;
            background: rgba(239, 68, 68, 0.04);
            align-items: center;
            gap: 0.75rem;
        }
        .plugin-management-failed-icon {
            display: inline-flex;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background: rgba(239, 68, 68, 0.12);
            color: #dc2626;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
        }
        .plugin-management-failed-icon svg {
            width: 1.125rem;
            height: 1.125rem;
        }
        .plugin-management-failed-content {
            display: flex;
            min-width: 0;
            flex: 1;
            flex-direction: column;
        }
        .plugin-management-failed-content strong {
            overflow: hidden;
            font-size: 0.875rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .plugin-management-failed-content small {
            display: -webkit-box;
            margin-top: 0.125rem;
            overflow: hidden;
            color: #dc2626;
            font-size: 0.6875rem;
            line-height: 1.4;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
        }
        /* 插件空状态 */
        .plugin-management-empty {
            display: flex;
            min-height: 12rem;
            border: 1px dashed rgba(107, 114, 128, 0.3);
            border-radius: 0.75rem;
            color: #9ca3af;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 0.5rem;
        }
        .plugin-management-empty.is-compact {
            min-height: 8rem;
        }
        .plugin-management-empty svg {
            width: 1.75rem;
            height: 1.75rem;
        }
        .plugin-management-empty span {
            font-size: 0.75rem;
        }
        @media (max-width: 1024px) {
            .plugin-management-layout {
                grid-template-columns: minmax(0, 1fr);
            }
        }
        @media (max-width: 640px) {
            .plugin-management-side {
                padding: 1rem;
            }
        }
    </style>
</x-filament-panels::page>
