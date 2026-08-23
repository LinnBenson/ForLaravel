## To View
- To View 提供项目公共页面框架、主题变量、基础样式、Bootstrap Icons 以及全局 JavaScript 工具
- 继承 `View::Frame` 的 Blade 页面会自动加载 `Assets/js/Core.js`，无需再次引入
  - `@extends( 'View::Frame' )`

## 预设组件
- 卡片组件 [Views/Components/Card.blade.php]
  ```
    <x-View::Card title="卡片标题" icon="卡片图标" open="true:默认打开|false:默认关闭|off:禁用" padding="内边距设置">
        卡片内容
    </x-View::Card>
  ```
- 按钮组件 [Views/Components/Button.blade.php]
  ```
    <x-View::Button icon="按钮图标" size="small|default|big" color="r0|r1|r2|r3|r4|r5|r6" href="跳转链接" target="打开方式" border:线条按钮 stop:禁用按钮 loading:加载中>
        按钮文本
    </x-View::Button>
  ```
- 布局组件 [Views/Components/Layout.blade.php]
  ```
    // default: 间隔页面 gap="0px:间距"
    // row: 行均匀居中分布 gap="0px:间距"
    // list: 网格布局 gap="0px:间距" min="80px:元素最小宽度"
    <x-View::Layout gap="间距设置" min="元素最小宽度" default|row|list>
        布局内容
    </x-View::Layout>
  ```
- 二维码组件 [Views/Components/QRCode.blade.php]
  ```
    <x-View::QRCode size="二维码尺寸">二维码内容</x-View::QRCode>
  ```
- 输入组件 [Views/Components/Input.blade.php]
  ```
    <form onsubmit="Core.submit( this, successMethod ); return false;">
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
        <x-View::Input title="Markdown" type="markdown" name="markdown" left="auto" value="Markdown"  required />
        <x-View::Input title="验证码" type="verify" name="verify" placeholder="请输入验证码" left="auto" required />
        <x-View::Input title="自定义验证码" type="verify" name="custom_verify" link="/custom/verify" placeholder="请输入验证码" left="auto" required />
        <x-View::Input title="发送代码" type="send" name="send" placeholder="请输入发送代码" bind="email" link="/api/send/code" max="60" left="auto" required />
        <x-View::Input title="同意协议" type="agree" name="agree" placeholder="以上内容本人已同意保存" left="auto" required />
        <x-View::Input title="上传文件" type="upload" name="upload" min="1" max="2" exts="jpg,png" left="auto" value="" />
        <x-View::Input type="button" left="auto">
            <x-View::Button icon="bi-star" type="submit">提交</x-View::Button>
        </x-View::Input>
    </form>
  ```

## 核心对象 [Assets/js/Core.js]
- 站点是否已初始化: Boolean `Core.initialized`
  - Core.js 加载完成后会自动执行 `Core.init()`，初始化完成后为 true
- 当前请求标识: String `Core.rid`
  - 当前请求的唯一标识，由公共页面框架注入
- 当前语言代码: String `Core.locale`
  - 当前页面语言代码，例如 `zh-CN`
- 页面宽度: Number|null `Core.width`
  - 当前浏览器视口宽度，初始化前为 null
- 页面高度: Number|null `Core.height`
  - 当前浏览器视口高度，初始化前为 null
- 系统信息: Object `Core.app`
  - `/api/index` 返回的应用信息，包含应用标题、调试状态、访问地址、图标和版权信息
- 用户信息: Object|null `Core.user`
  - `/api/index` 返回的当前用户信息，未登录或登录状态失效时为 null
- 缓存数据: Object `Core.cache`
  - 页面生命周期内可使用的公共缓存对象，`lang` 保存首次请求获取的语言包，`refreshSystemInfoInterval` 保存系统信息定时刷新器 ID
- 初始化站点
  - `Core.init()`
  - 获取浏览器视口尺寸并写入 `Core.width` 和 `Core.height`
  - 设置 CSS 变量 `--vw` 和 `--vh`，并在窗口尺寸变化时实时更新
  - 从页面注入数据和本地存储中初始化 RID、语言及系统信息，RID 无效时自动生成 UUID，语言无效时使用 `en`
  - 将 RID 写入 localStorage 和作用路径为 `/` 的 Cookie
  - 初始化系统信息，并每 10 秒调用一次 `Core.refreshSystemInfo()`
  - Core.js 加载完成后会自动执行，一般不需要手动调用
  - return [void]
- 获取请求头
  - `Core.headers()`
  - 返回包含当前 `rid` 和 `locale` 的 HTTP 请求头对象
  - localStorage 中存在非空 token 字符串时，追加 `Authorization: Bearer {token}` 请求头
  - return [object]HTTP 请求头对象
- 创建 Web 请求
  - `Core.web( [string]请求链接 )`
  - 创建基于 jQuery Ajax 的链式请求构建对象，默认使用 GET 方法、`Core.headers()` 请求头并启用标准响应自动检查
  - 支持的方法如下：
    - `.method( [string]请求方法 )` 设置 HTTP 请求方法，方法名会自动转换为大写
    - `.data( [object|FormData|string]请求数据 )` 设置请求数据；普通对象会与已有数据合并，其他有效数据类型会替换已有数据
    - `.header( [object]请求头 )` 将请求头合并到默认请求头中
    - `.timeout( [number]超时时间 )` 设置请求超时时间，单位为毫秒，`0` 表示不限制
    - `.success( [Function]成功回调 )` 设置业务成功回调；启用自动检查时，回调参数为标准响应中的 `data`
    - `.error( [Function]错误回调 )` 设置请求错误回调；参数与 jQuery Ajax 的 error 回调一致
    - `.loading( [Function]加载回调 )` 设置加载状态回调，请求开始时接收 `true`，请求结束时接收 `false`
    - `.download( [Function]下载进度回调 )` 设置下载进度回调，依次接收完成百分比、已下载字节数和总字节数
    - `.upload( [Function]上传进度回调 )` 设置上传进度回调，依次接收完成百分比、已上传字节数和总字节数，并自动设置 `processData: false` 和 `contentType: false`
    - `.check( [boolean]是否自动检查 )` 控制是否使用内置方法处理标准响应，默认为 `true`
    - `.request( [object]Ajax 配置 = {} )` 合并配置并发起请求；传入的配置优先级最高
  - 自动检查开启时，响应应使用 `echoJson()` 的标准结构；`success` 状态执行成功回调，`info`、`error` 和 `warning` 状态由全局通知处理
  - HTTP 401 响应会清理失效的本地登录信息；存在 token 时显示登录失效通知并刷新页面
  - 关闭自动检查后，成功和失败响应分别原样传递给 `.success()` 与 `.error()` 回调
  - `.request()` 返回 jqXHR 对象，可继续调用 `.done()`、`.fail()`、`.always()` 或 `.abort()`
- 创建弹窗
  - `Core.popup( [string|jQuery]弹窗内容 )`
  - 支持的方法如下：
    - `.title( [string]标题 )` 设置弹窗标题
    - `.icon( [string]Bootstrap Icons 类名 )` 设置标题图标，例如 `bi-star`
    - `.close( [boolean]是否显示关闭按钮 )` 设置右上角关闭按钮，默认为 `true`
    - `.mask( [boolean]是否显示遮罩 )` 设置弹窗遮罩，默认为 `true`
    - `.width( [string]宽度 )` 设置弹窗宽度，例如 `600px` 或 `80vw`
    - `.height( [string]高度 )` 设置弹窗高度，例如 `400px` 或 `80vh`
    - `.rid( [string]唯一标识 )` 设置弹窗 RID；显示相同 RID 的弹窗时会先关闭已有弹窗
    - `.button( [object]按钮配置 )` 添加一个按钮；配置支持 `title` 按钮文本、`icon` 图标和 `method` 点击回调
    - `.buttons( [array]按钮配置列表 )` 替换全部按钮配置
    - `.show()` 将配置完成的弹窗添加到页面
  - return [object]可链式配置的弹窗对象
- 关闭弹窗
  - `Core.popupClose( [string]弹窗唯一标识 )`
  - 查找并移除具有指定 RID 的弹窗；目标不存在时不执行操作
  - 例如 `Core.popupClose( 'notice-popup' )`
  - return [void]
- 刷新系统信息
  - `Core.refreshSystemInfo( [string|null]语言包名称 = null )`
  - 携带 `Core.headers()` 返回的请求头向 `/api/index` 发起 GET 请求
  - 语言包名称使用竖线分隔，例如 `base|validation`；传入后通过 `langs` GET 参数请求对应语言包
  - 语言代码由 `Core.headers()` 的 `Locale` 请求头传递，并由服务端请求中间件校验和设置
  - 请求成功时更新 `Core.app` 和 `Core.user`，并将系统信息和有效用户信息写入 localStorage
  - 响应包含语言包时合并到 `Core.cache.lang`
  - 用户状态失效时清除本地用户信息；存在 token 时同时删除 token 并刷新页面
  - `Core.init()` 初始化时请求 `base|validation`，之后每 10 秒自动刷新时不重复请求语言包
  - return [void]
- 显示通知消息
  - `Core.toast( [number|false]通知状态, [string]通知标题 = '', [string]通知内容 = '', [number]显示时长 = 8000 )`
  - 通知状态支持 `0` 成功、`1` 消息、`2` 错误、`3` 警告；传入 `false` 时立即关闭当前通知
  - 显示时长单位为毫秒，传入 `0` 时不自动关闭，重复调用会替换当前通知并重新计时
  - 例如 `Core.toast( 0, '保存成功', '数据已保存', 3000 )`
  - 例如 `Core.toast( false )` 手动关闭当前通知
  - return [void]
- 控制全屏加载状态
  - `Core.loading( [boolean]是否显示 = true, [number]自动关闭时间 = 0 )`
  - 显示或关闭页面全屏加载遮罩
  - 自动关闭时间单位为毫秒，传入 `0` 时不自动关闭
  - 例如 `Core.loading()` 显示全屏加载遮罩
  - 例如 `Core.loading( true, 3000 )` 显示遮罩并在 3 秒后自动关闭
  - 例如 `Core.loading( false )` 手动关闭全屏加载遮罩
  - return [void]
- 控制元素加载状态
  - `Core.boxLoading( [jQuery]目标元素, [boolean]是否显示 = true, [number]自动关闭时间 = 0 )`
  - 在目标 jQuery 元素内部显示或关闭加载遮罩
  - 自动关闭时间单位为毫秒，传入 `0` 时不自动关闭
  - 例如 `Core.boxLoading( $( '#card' ) )` 在指定元素内显示加载遮罩
  - 例如 `Core.boxLoading( $( '#card' ), true, 3000 )` 显示遮罩并在 3 秒后自动关闭
  - 例如 `Core.boxLoading( $( '#card' ), false )` 手动关闭指定元素的加载遮罩
  - return [void]
- 生成二维码
  - `Core.QRCode( [jQuery]目标元素, [string]二维码内容, [number]尺寸 = 128, [object]其他配置 = {} )`
  - 在目标 jQuery 元素内部生成二维码；生成前会清空目标元素，并移除 QRCode.js 自动添加的 `title` 属性
  - 宽度和高度默认均为 128 像素，默认深色为 `#2b2b2b`、浅色为 `#eeeeee`，纠错等级为 `QRCode.CorrectLevel.H`
  - 其他配置会覆盖同名默认配置，可传入 QRCode.js 支持的 `width`、`height`、`colorDark`、`colorLight`、`correctLevel` 等选项
  - 目标参数必须是包含有效 DOM 元素的 jQuery 对象，例如 `$( '#qrcode' )`
  - 例如 `Core.QRCode( $( '#qrcode' ), 'https://example.com', 160 )` 生成 160×160 像素的二维码
  - 例如 `Core.QRCode( $( '#qrcode' ), 'Hello', 128, { colorDark: '#000000', correctLevel: QRCode.CorrectLevel.M } )` 自定义颜色和纠错等级
  - return [void]
- 复制文本
  - `await Core.copy( [string]复制内容 )`
  - return [Promise<boolean>]复制成功返回 true，内容无效、权限被拒绝或复制失败时返回 false
- 获取表单数据
  - `Core.submit( [HTMLFormElement]表单元素, [Function|null]成功回调 = null )`
  - 读取表单内所有带有 `input` 属性的 ToView 输入组件；没有 `name`、没有组件 `type` 或已禁用的输入不会写入结果
  - 普通输入、选择框和长文本按原值返回；开关返回 Boolean，单选返回选中值或 null，多选返回 Array
  - 手机号会移除区号和号码中的非数字字符，并格式化为 `+区号 号码`；区号或号码不完整时返回 null
  - `datetime-local` 返回 `YYYY.MM.DD HH:mm:ss`，`date` 返回 `YYYY.MM.DD`，`time` 返回 `HH:mm:ss`；没有填写时返回 null
  - 多选框名称支持 `name="字段名[]"`，返回对象中的键名会自动移除末尾的 `[]`
  - 自动校验带有 `required` 属性的 ToView 输入组件；校验失败时显示错误通知、临时标记错误组件并返回 false
  - 校验成功时返回表单数据对象；传入回调函数后，会先将表单数据传给回调，再返回同一个数据对象
  - 建议在表单的 `onsubmit` 中调用并返回 false，避免浏览器执行原生提交和刷新页面
  - 例如 `onsubmit="Core.submit( this, ( data ) => { console.table( data ); } ); return false;"`
  - return [object|false]表单数据对象，表单无效或必填校验失败时返回 false
- 获取翻译文本
  - `t( [string]翻译键名, [object]占位符替换参数 = {} )`
  - 使用点号键从 `Core.cache.lang` 读取翻译，例如 `base.save` 或 `validation.required`
  - 支持 Laravel 风格的 `:name`、`:NAME` 和 `:Name` 占位符，并根据占位符形式转换替换文本大小写
  - 语言包未加载、翻译键不存在或目标内容不是字符串时返回原翻译键名，键名为空时返回空字符串
  - return [string]翻译文本或原翻译键名
- 判断字符串是否为 JSON
  - `is_json( [mixed]待判断的值 )`
  - 只接受合法的 JSON 对象或 JSON 数组字符串，JSON 基础值不会被视为符合条件
  - return [bool]是否为合法 JSON 对象或 JSON 数组字符串
- 判断值是否为空
  - `empty( [mixed]待判断的值 )`
  - `undefined`、`null`、`false`、`0`、`0n`、空字符串及字符串 `'0'` 返回 true
  - 空数组、空普通对象、空 Map 和空 Set 返回 true
  - return [bool]是否为空
- 生成 UUID
  - `uuid()`
  - 优先使用浏览器原生 `crypto.randomUUID()`，不支持时使用兼容实现生成 UUID v4
  - return [string]标准 UUID 字符串
- 判断字符串是否为 UUID
  - `is_uuid( [mixed]待判断的值 )`
  - 字母不区分大小写
  - return [bool]是否为标准 `8-4-4-4-12` UUID 字符串
- 获取本地存储数据
  - `get( [string]存储键名, [mixed]默认值 = null )`
  - 通过 `set()` 保存的对象、数组、数字、布尔值及 null 会恢复为原类型，普通字符串保持字符串类型
  - 键不存在、键名无效、数据损坏或浏览器拒绝访问存储时返回默认值
  - return [mixed]已保存的数据或默认值
- 保存本地存储数据
  - `set( [string]存储键名, [mixed]待保存的数据 )`
  - 字符串按原值保存，其它可序列化值使用带内部标识的 JSON 格式保存
  - 不支持保存 undefined、函数、Symbol、BigInt 或存在循环引用的数据
  - return [bool]写入成功返回 true，参数无效、序列化失败或存储失败返回 false
- 删除本地存储数据
  - `del( [string]存储键名 )`
  - 删除不存在的键也视为成功
  - return [bool]删除成功返回 true，键名无效或存储访问失败返回 false
- 获取 Cookie 数据
  - `cookieGet( [string]Cookie 键名, [mixed]默认值 = null )`
  - 通过 `cookieSet()` 保存的对象、数组、数字、布尔值及 null 会恢复为原类型，普通字符串保持字符串类型
  - Cookie 不存在、键名无效、数据损坏或读取失败时返回默认值
  - JavaScript 无法读取带有 HttpOnly 属性的 Cookie
  - return [mixed]已保存的数据或默认值
- 保存 Cookie 数据
  - `cookieSet( [string]Cookie 键名, [mixed]待保存的数据, [object]Cookie 选项 = {} )`
  - 字符串按原值保存，其它可序列化值使用带内部标识的 JSON 格式保存
  - Cookie 选项支持 `expires`、`path`、`domain` 和 `secure`，默认 path 为 `/`
  - 不支持保存 undefined、函数、Symbol、BigInt 或存在循环引用的数据
  - return [bool]写入并验证成功返回 true，参数无效、序列化失败或写入失败返回 false
- 删除 Cookie 数据
  - `cookieDel( [string]Cookie 键名, [object]Cookie 选项 = {} )`
  - Cookie 选项支持 `path` 和 `domain`，默认 path 为 `/`；删除时应传入与写入时相同的 path 和 domain
  - Cookie 不存在时也视为删除成功
  - return [bool]删除成功返回 true，参数无效或删除失败返回 false

## 提交服务 [Support/PostService.php]
- 验证图片验证码
  - `PostService::verifyCode( [Request]请求对象, [string]用户输入的验证码 )`
  - 可通过 `\App\Plugins\ToView\Support\PostService::verifyCode( $request, $request->string( 'verify' )->toString() )` 调用
  - 验证码图片可通过 `route( 'plugins.to-view.verify' )` 获取，生成时会根据 `verify` 配置将验证码和过期时间保存到当前 Session
  - 验证接口必须使用 `web` 中间件，并与验证码图片请求使用同一个 Session
  - 验证时不区分字母大小写；验证码错误、缺失或过期时返回 false，正确时返回 true
  - 每个验证码只能验证一次，无论验证结果是否正确，调用后都会从 Session 中删除
  - 例如 `if ( !PostService::verifyCode( $request, $request->string( 'verify' )->toString() ) ) { return echoJson( 2, ['validation.verify'], 422 ); }`
  - return [bool]验证码有效且匹配时返回 true，否则返回 false
- 移动临时文件
  - `PostService::moveTmp( [string]临时文件链接, [string]目标目录 )`
  - 可通过 `\App\Plugins\ToView\Support\PostService::moveTmp()` 调用
  - 将 ToView 上传组件返回的临时文件移动到 `storage/app/public` 下的正式目录，移动后保留上传时生成的 UUID 文件名
  - 临时文件链接支持站内相对链接，也支持路径与当前临时文件路由一致的完整链接
  - 目标目录必须是相对于 `storage/app/public` 的目录，例如 `avatars` 对应 `storage/app/public/avatars`，不存在时会自动创建
  - 仅允许移动当前上传配置支持、且仍存在于临时上传目录中的 UUID 文件
  - 非临时文件链接、绝对路径、目录穿越、目标超出 `storage/app/public` 或目标文件已存在时返回 false
  - 例如 `PostService::moveTmp( '/internal-plugins-to-view/tmp/550e8400-e29b-41d4-a716-446655440000.png', 'avatars' )`
  - 上述示例成功时返回 `/storage/avatars/550e8400-e29b-41d4-a716-446655440000.png`
  - return [string|false]移动成功返回不含 Host 的公开访问链接，链接、路径或文件无效以及移动失败时返回 false

## 视图服务 [Support/FrameService.php]
- 获取框架视图数据
  - `ViewService::renderFrame()`
  - 用于公共 Blade 框架初始化，可在视图中通过 `$frame = \App\Plugins\ToView\Support\FrameService::renderFrame()` 调用
  - return [object]包含当前主题配置 `theme` 和将下划线转换为连字符的当前语言环境 `locale`
- 获取当前主题配置
  - `ViewService::getTheme()`
  - 根据 `theme` Cookie 从 `setting( 'app.theme' )` 中选择当前主题；Cookie 对应的主题不存在或配置无效时使用 `Default` 主题
  - return [array]当前主题配置
