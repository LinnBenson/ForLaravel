<div class="plugin-market">
    {{-- 插件市场搜索栏 --}}
    <div class="plugin-market-search">
        <label>
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <input
                wire:model="marketKeyword"
                wire:keydown.enter.prevent="searchMarket"
                type="search"
                placeholder="搜索插件标识、名称或描述"
            >
        </label>
        <x-filament::button class="plugin-market-search-button" type="button" color="primary" icon="heroicon-o-magnifying-glass" wire:click="searchMarket">
            搜索
        </x-filament::button>
    </div>

    {{-- 插件市场列表 --}}
    <div class="plugin-market-list" wire:loading.class="is-loading" wire:target="searchMarket,previousMarketPage,nextMarketPage">
        @forelse ( $this->marketPackages as $package )
            <article>
                <span class="plugin-market-icon"><x-filament::icon icon="heroicon-o-cube" /></span>
                <div class="plugin-market-content">
                    <header>
                        <div>
                            <strong>{{ $package['name'] ?? $package['rid'] ?? '未知插件' }}</strong>
                            <code>{{ $package['rid'] ?? '--' }}</code>
                        </div>
                        <span class="plugin-market-version">v{{ $package['version'] ?? '--' }}</span>
                    </header>
                    <p>{{ $package['description'] ?? '暂无插件描述' }}</p>
                    <div class="plugin-market-bottom">
                        <footer class="plugin-market-meta">
                            <span><x-filament::icon icon="heroicon-o-user" />{{ ( $package['author'] ?? '' ) !== '' ? $package['author'] : 'Unknown' }}</span>
                            <span><x-filament::icon icon="heroicon-o-arrow-down-tray" />{{ (int) ( $package['count'] ?? 0 ) }}</span>
                        </footer>
                        <div class="plugin-market-actions">
                            @if ( $this->marketInstalled[$package['rid'] ?? ''] ?? false )
                                <em><x-filament::icon icon="heroicon-o-check-circle" />已安装</em>
                            @elseif ( isset( $this->marketInstalled[$package['rid'] ?? ''] ) )
                                <x-filament::button
                                    type="button"
                                    size="xs"
                                    color="primary"
                                    icon="heroicon-o-arrow-down-tray"
                                    wire:click="installMarketPlugin( {{ \Illuminate\Support\Js::from( $package['rid'] ) }} )"
                                    wire:loading.attr="disabled"
                                    wire:target="installMarketPlugin"
                                >
                                    安装
                                </x-filament::button>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @empty
            <div class="plugin-market-empty">
                <x-filament::icon icon="heroicon-o-shopping-bag" />
                <strong>暂无插件</strong>
                <p>请更换关键词后重试。</p>
            </div>
        @endforelse
    </div>

    {{-- 插件市场分页 --}}
    <footer class="plugin-market-pagination">
        <span>共 {{ $this->marketTotal }} 个插件，第 {{ $this->marketPage }} / {{ $this->marketQuantity }} 页</span>
        <div>
            <x-filament::button color="gray" size="sm" wire:click="previousMarketPage" :disabled="$this->marketPage <= 1">上一页</x-filament::button>
            <x-filament::button color="gray" size="sm" wire:click="nextMarketPage" :disabled="$this->marketPage >= $this->marketQuantity">下一页</x-filament::button>
        </div>
    </footer>
</div>

<style>
    /* 插件市场弹窗 */
    .plugin-market-modal { max-height: calc(100dvh - 2rem); overflow: hidden; }
    .plugin-market-modal .fi-modal-content { flex: 1 1 auto; min-height: 0; overflow: hidden; }
    .plugin-market { display: flex; height: 100%; min-height: 0; flex-direction: column; gap: 1rem; }
    .plugin-market-search { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: stretch; gap: 0.75rem; }
    .plugin-market-search label { display: flex; min-width: 0; min-height: 2.6rem; padding: 0 0.8rem; border: 1px solid var(--gray-300); border-radius: 0.65rem; background: white; align-items: center; gap: 0.55rem; }
    .dark .plugin-market-search label { border-color: var(--gray-700); background: var(--gray-900); }
    .plugin-market-search label:focus-within { border-color: var(--primary-500); box-shadow: 0 0 0 1px var(--primary-500); }
    .plugin-market-search svg { width: 1.1rem; height: 1.1rem; color: var(--gray-400); }
    .plugin-market-search input { width: 100%; min-height: 2.5rem; border: 0; outline: 0; background: transparent; color: var(--gray-950); font-size: 0.85rem; }
    .dark .plugin-market-search input { color: white; }
    .plugin-market-search-button { min-width: 6.5rem; justify-content: center; }
    .plugin-market-list { display: grid; min-height: 0; overflow-x: hidden; overflow-y: auto; flex: 1; align-content: start; grid-template-columns: minmax(0, 1fr); gap: 0.5rem; overscroll-behavior: contain; transition: opacity 0.15s; -webkit-overflow-scrolling: touch; }
    .plugin-market-list.is-loading { pointer-events: none; opacity: 0.5; }
    .plugin-market-list article { display: flex; min-width: 0; padding: 0.75rem 0.85rem; border: 1px solid var(--gray-200); border-radius: 0.7rem; background: white; gap: 0.7rem; }
    .dark .plugin-market-list article { border-color: var(--gray-700); background: var(--gray-900); }
    .plugin-market-icon { display: flex; width: 2.25rem; height: 2.25rem; flex: 0 0 auto; border-radius: 0.6rem; background: color-mix(in srgb, var(--primary-500) 12%, transparent); color: var(--primary-600); align-items: center; justify-content: center; }
    .plugin-market-icon svg { width: 1.1rem; height: 1.1rem; }
    .plugin-market-content { display: flex; min-width: 0; flex: 1; flex-direction: column; }
    .plugin-market-content header { display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem; }
    .plugin-market-content header > div { display: flex; min-width: 0; flex-direction: column; }
    .plugin-market-content strong { overflow: hidden; color: var(--gray-950); font-size: 0.9rem; text-overflow: ellipsis; white-space: nowrap; }
    .dark .plugin-market-content strong { color: white; }
    .plugin-market-content code { margin-top: 0.1rem; color: var(--gray-500); font-size: 0.7rem; }
    .plugin-market-version { padding: 0.2rem 0.45rem; border-radius: 9999px; background: color-mix(in srgb, var(--primary-500) 10%, transparent); color: var(--primary-700); font-size: 0.7rem; font-weight: 650; }
    .plugin-market-bottom { display: flex; min-height: 1.7rem; margin-top: auto; align-items: flex-end; justify-content: space-between; gap: 0.75rem; }
    .plugin-market-actions { display: flex; flex: 0 0 auto; align-items: center; justify-content: flex-end; gap: 0.55rem; }
    .plugin-market-actions em { display: inline-flex; color: var(--gray-500); align-items: center; gap: 0.25rem; font-size: 0.72rem; font-style: normal; font-weight: 600; }
    .plugin-market-actions em svg { width: 0.9rem; height: 0.9rem; }
    .plugin-market-content p { overflow: hidden; margin: 0.3rem 0; color: var(--gray-600); font-size: 0.76rem; line-height: 1.4; text-overflow: ellipsis; white-space: nowrap; }
    .dark .plugin-market-content p { color: var(--gray-400); }
    .plugin-market-meta { display: flex; min-width: 0; color: var(--gray-500); align-items: center; gap: 0.75rem; font-size: 0.68rem; }
    .plugin-market-meta span { display: inline-flex; align-items: center; gap: 0.25rem; }
    .plugin-market-meta svg { width: 0.85rem; height: 0.85rem; }
    .plugin-market-empty { display: flex; min-height: 18rem; grid-column: 1 / -1; color: var(--gray-500); align-items: center; justify-content: center; flex-direction: column; }
    .plugin-market-empty svg { width: 2.5rem; height: 2.5rem; margin-bottom: 0.65rem; }
    .plugin-market-empty strong { color: var(--gray-800); }
    .dark .plugin-market-empty strong { color: var(--gray-200); }
    .plugin-market-empty p { margin-top: 0.2rem; font-size: 0.78rem; }
    .plugin-market-pagination { display: flex; padding-top: 0.85rem; border-top: 1px solid var(--gray-200); color: var(--gray-500); align-items: center; justify-content: space-between; gap: 1rem; font-size: 0.78rem; }
    .dark .plugin-market-pagination { border-color: var(--gray-700); }
    .plugin-market-pagination > div { display: flex; gap: 0.5rem; }
    @media (max-width: 640px) {
        .plugin-market-modal { max-height: calc(100dvh - 1rem); }
        .plugin-market-modal .fi-modal-header { padding-inline: 1rem; padding-top: 1rem; }
        .plugin-market-modal .fi-modal-content,
        .plugin-market-modal .fi-modal-footer { padding-inline: 1rem; }
        .plugin-market-search { grid-template-columns: 1fr; }
        .plugin-market-search-button { width: 100%; }
        .plugin-market-pagination { align-items: stretch; flex-direction: column; }
        .plugin-market-pagination > div > * { flex: 1; }
    }
</style>
