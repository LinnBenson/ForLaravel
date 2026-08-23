@extends( 'View::Frame' )

@section( 'title', 'Welcome' )

@section( 'body' )
<div id="main" class="padding32 sizing">
    <x-View::Card class="welcome-intro" open="off" padding="0px">
        <section class="welcome-intro-content">
            <div class="welcome-intro-main">
                <div class="welcome-intro-mark" aria-hidden="true">
                    <x-View::QRCode size="100%" :config="[ 'color' => '#000', 'background' => '#eee' ]">{{setting( 'app.title', 'Laravel' )}}</x-View::QRCode>
                </div>
                <div class="welcome-intro-copy">
                    <span class="welcome-intro-eyebrow">TO VIEW COMPONENT LIBRARY</span>
                    <h1>{{setting( 'app.title', 'Laravel' )}}</h1>
                    <p>
                        基于 Laravel Blade 构建的公共页面组件与交互工具，提供统一主题、响应式布局、表单处理、文件上传和常用反馈能力。
                    </p>
                    <div class="welcome-intro-meta">
                        <span><i class="bi bi-box"></i> Laravel {{app()->version()}}</span>
                        <span><i class="bi bi-code-slash"></i> PHP {{PHP_VERSION}}</span>
                        <span><i class="bi bi-circle-fill"></i> {{ucfirst( app()->environment() )}}</span>
                    </div>
                </div>
            </div>
            <div class="welcome-intro-features" aria-label="主要能力">
                <div>
                    <i class="bi bi-palette"></i>
                    <span><strong>主题系统</strong><small>统一颜色与视觉规范</small></span>
                </div>
                <div>
                    <i class="bi bi-grid"></i>
                    <span><strong>Blade 组件</strong><small>快速组合常用页面结构</small></span>
                </div>
                <div>
                    <i class="bi bi-phone"></i>
                    <span><strong>响应式布局</strong><small>适配桌面与移动设备</small></span>
                </div>
                <div>
                    <i class="bi bi-lightning-charge"></i>
                    <span><strong>交互工具</strong><small>请求、通知、加载与表单</small></span>
                </div>
            </div>
        </section>
    </x-View::Card>
    <x-View::Card title="按钮预览" class="test" icon="bi-star" open="true">
        <x-View::Layout class="bottom8" gap="8px">
            <x-View::Button icon="bi-link-45deg" color="r2" onClick="Core.toast( 3, '通知标题', '通知内容通知内容通知内容通知内容通知内容通知内容通知内容' )">通知测试</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" onClick="Core.loading( true, 3000 )">加载测试</x-View::Button>
            <x-View::Button icon="bi-check-circle" color="r4" onClick="Core.boxLoading( $( '.test' ), true, 3000 )">卡片加载</x-View::Button>
            <x-View::Button icon="bi-exclamation-circle" color="r5" class="copy" data-clipboard-text="复制成功">点击复制</x-View::Button>
            <x-View::Button icon="bi-star" color="r3" loading>按钮 2</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" size="big" onClick="
                Core.popup( '123456' ).icon( 'bi-star' ).button({
                    icon: 'bi-star',
                    title: '测试按钮',
                    method: () => {
                        Core.toast( 0, '按钮点击', '你点击了弹窗按钮' );
                    }
                }).button({
                    icon: 'bi-star',
                    title: '测试按钮',
                    method: () => {
                        Core.toast( 0, '按钮点击', '你点击了弹窗按钮' );
                    }
                }).title( '弹窗标题' ).show()
            ">点击弹窗</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" size="small">按钮 2</x-View::Button>
        </x-View::Layout>
        <x-View::Layout class="bottom8" gap="8px">
            <x-View::Button icon="bi-link-45deg" color="r2" border>按钮 1</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" border>按钮 2</x-View::Button>
            <x-View::Button icon="bi-check-circle" color="r4" border>按钮 3</x-View::Button>
            <x-View::Button icon="bi-exclamation-circle" color="r5" border>按钮 4</x-View::Button>
            <x-View::Button icon="bi-star" color="r3" border loading>按钮 2</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" size="big" border>按钮 2</x-View::Button>
            <x-View::Button icon="bi-cart2" color="r3" size="small" border>按钮 2</x-View::Button>
        </x-View::Layout>
    </x-View::Card>
    <x-View::Card title="演示表单" icon="bi-star" open="true">
        <form onsubmit="Core.submit( this, submitData ); return false;">
            <x-View::Input title="普通文本" type="text" name="text" placeholder="请输入一段文本" left="auto" :tips="[
                '这是第一个提示',
                '这是第二个提示',
            ]" value="text" required />
            <x-View::Input title="密码框" type="password" name="password" placeholder="请输入一段密码" left="auto" value="123456" required error />
            <x-View::Input title="邮箱输入" type="email" name="email" placeholder="请输入一段邮箱" left="auto" value="abc@adb.com" required />
            <x-View::Input title="手机号" type="phone" name="phone" placeholder="请输入手机号" left="auto" value="+1 123456" required />
            <x-View::Input title="开关按钮" type="switch" name="switch" left="auto" value="1" required />
            <x-View::Input title="数字输入" type="number" name="number" placeholder="请输入一段数字" left="auto" step="0.01" min="0" max="100" value="24" required />
            <x-View::Input title="完整日期" type="datetime-local" name="datetime-local" placeholder="请输入完整日期" left="auto" value="2024.06.06 12:00:00" required />
            <x-View::Input title="日期输入" type="date" name="date" placeholder="请输入日期" left="auto" value="2024.06.06" required />
            <x-View::Input title="时间输入" type="time" name="time" placeholder="请输入时间" left="auto" value="12:00:00" required />
            <x-View::Input title="单选输入" type="radio" name="radio" left="auto" :options="[
                'option1' => '选项 1',
                'option2' => '选项 2'
            ]" value="option2" required />
            <x-View::Input title="多选输入" type="checkbox" name="checkbox" left="auto" :options="[
                'option1' => '选项 1',
                'option2' => '选项 2'
            ]" value="option1|option2" required />
            <x-View::Input title="选择输入" type="select" name="select" left="auto" :options="[
                'option1' => '选项 1',
                'option2' => '选项 2'
            ]" value="option2" required />
            <x-View::Input title="长文本输入" type="textarea" name="textarea" placeholder="请输入一段文本" left="auto" />
            <x-View::Input title="代码输入" type="code" name="code" placeholder="请输入一段代码" left="auto" />
            <x-View::Input title="颜色输入" type="color" name="color" value="#2b2b2b" left="auto" required />
            <x-View::Input title="Markdown" type="markdown" name="markdown" left="auto" value="Markdown" required />
            <x-View::Input title="发送代码" type="send" name="send" placeholder="请输入发送代码" bind="email" link="/api/send/code" max="60" left="auto" required />
            <x-View::Input title="验证码" type="verify" name="verify" placeholder="请输入发送验证码" left="auto" required />
            <x-View::Input title="上传文件" type="upload" name="upload" max="2" exts="jpg,png" left="auto" value="" />
            <x-View::Input type="agree" name="agree" placeholder="以上内容本人已同意保存" left="auto" required />
            <x-View::Input type="button" left="auto">
                <x-View::Button icon="bi-star" type="submit">提交</x-View::Button>
            </x-View::Input>
        </form>
    </x-View::Card>
</div>
@endsection

@push( 'footer' )
<script>
    function submitData( el ) {
        Core.web( '/debug' )
            .method( 'POST' )
            .data( el )
            .loading(( s ) => { Core.loading( s ); })
            .success(( res ) => {
                console.table( res );
            })
            .request();
    }
</script>
@endpush

@push( 'head' )
<style>
/* Welcome introduction */
section.welcome-intro {
    overflow: hidden;
    border-color: rgb( var( --r3 ), 0.2 );
    background:
        radial-gradient( circle at 92% 5%, rgb( var( --r3 ), 0.16 ), transparent 32% ),
        rgb( var( --r6 ) );
}
section.welcome-intro div.toview-card-content { padding: 0; }
section.welcome-intro section.welcome-intro-content { display: grid; grid-template-columns: minmax( 0, 1.45fr ) minmax( 280px, 0.75fr ); }
section.welcome-intro div.welcome-intro-main {
    display: flex;
    align-items: center;
    gap: 22px;
    min-width: 0;
    padding: 34px;
}
section.welcome-intro div.welcome-intro-mark {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 74px;
    width: 74px;
    height: 74px;
    overflow: hidden;
    border-radius: calc( var( --radius ) * 2.5 );
    background-color: rgb( var( --r3 ) );
    box-shadow: 0 12px 30px rgb( var( --r3 ), 0.22 );
    color: rgb( var( --r3c ) );
    font-size: 32px;
}
section.welcome-intro div.welcome-intro-mark img { width: 100%; height: 100%; object-fit: cover; }
section.welcome-intro div.welcome-intro-copy { min-width: 0; }
section.welcome-intro span.welcome-intro-eyebrow { color: rgb( var( --r3 ) ); font-size: 11px; font-weight: bold; letter-spacing: 0.12em; }
section.welcome-intro h1 { margin: 5px 0 8px; color: rgb( var( --r1 ) ); font-size: clamp( 24px, 3vw, 36px ); line-height: 1.2; }
section.welcome-intro p { max-width: 700px; margin: 0; color: rgb( var( --r1 ), 0.68 ); line-height: 1.75; }
section.welcome-intro div.welcome-intro-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
section.welcome-intro div.welcome-intro-meta span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 9px;
    border-radius: 999px;
    background-color: rgb( var( --r3 ), 0.09 );
    color: rgb( var( --r1 ), 0.72 );
    font-size: 11px;
}
section.welcome-intro div.welcome-intro-meta span:last-child i { color: rgb( var( --r4 ) ); font-size: 7px; }
section.welcome-intro div.welcome-intro-features {
    display: grid;
    grid-template-columns: repeat( 2, minmax( 0, 1fr ) );
    gap: 1px;
    border-left: 1px solid rgb( var( --r1 ), 0.09 );
    background-color: rgb( var( --r1 ), 0.09 );
}
section.welcome-intro div.welcome-intro-features > div {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 18px;
    background-color: rgb( var( --r6 ), 0.94 );
}
section.welcome-intro div.welcome-intro-features > div > i { flex: 0 0 auto; color: rgb( var( --r3 ) ); font-size: 20px; }
section.welcome-intro div.welcome-intro-features span { display: grid; min-width: 0; }
section.welcome-intro div.welcome-intro-features strong { color: rgb( var( --r1 ) ); font-size: 13px; }
section.welcome-intro div.welcome-intro-features small { margin-top: 3px; color: rgb( var( --r1 ), 0.52 ); font-size: 10px; line-height: 1.45; }
@media ( max-width: 860px ) {
    section.welcome-intro section.welcome-intro-content { grid-template-columns: minmax( 0, 1fr ); }
    section.welcome-intro div.welcome-intro-features { border-top: 1px solid rgb( var( --r1 ), 0.09 ); border-left: 0; }
}
@media ( max-width: 580px ) {
    div#main { padding: 16px; }
    section.welcome-intro div.welcome-intro-main { align-items: flex-start; gap: 14px; padding: 22px 18px; }
    section.welcome-intro div.welcome-intro-mark { flex-basis: 52px; width: 52px; height: 52px; border-radius: calc( var( --radius ) * 1.7 ); font-size: 23px; }
    section.welcome-intro div.welcome-intro-features { grid-template-columns: minmax( 0, 1fr ); }
    section.welcome-intro div.welcome-intro-features > div { padding: 14px 18px; }
}
</style>
@endpush
