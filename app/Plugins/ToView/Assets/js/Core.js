/**
 * 站点核心对象
 * 保存服务端注入的公共运行参数，并提供全站统一初始化入口。
 * @property {string} rid 当前请求的唯一标识
 * @property {string} locale 当前页面语言代码
 * @property {Function} init 站点初始化方法
 */
window['Core'] = {
    // 站点是否已初始化
    initialized: false,
    // 当前请求标识，由公共页面框架注入。
    rid: null,
    // 当前页面语言，由公共页面框架注入。
    locale: null,
    // 页面宽度 / 页面高度
    width: null, height: null,
    // 系统信息
    app: {},
    // 用户信息
    user: null,
    // 剪贴板对象
    clipboard: null,
    // 缓存数据
    cache: {
        lang: get( 'langs' ) || {}, // 语言包缓存
        RefreshSystemInfoInterval: null, // 刷新系统信息定时器
        ToastTimeout: null, // 通知消息定时器
        LoadingTimeout: null // 全屏加载定时器
    },

    /**
     * 初始化站点
     * 作为全站公共 JavaScript 的统一启动入口，在核心对象创建后执行。
     * @returns {void}
     */
    init: function() {
        // 监听窗口尺寸变化事件，更新全局宽高属性。
        const resize = () => {
            Core.width = window.innerWidth;
            Core.height = window.innerHeight;
            document.documentElement.style.setProperty( '--vw', `${Core.width}px` );
            document.documentElement.style.setProperty( '--vh', `${Core.height}px` );
        };
        resize();
        window.addEventListener( 'resize', resize );
        // 数据刷新
        const values = [ 'rid', 'locale', 'app' ];
        for ( const key of values ) {
            if ( !empty( setting[key] ) ) {
                this[key] = setting[key];
            }else if ( get( key ) ) {
                this[key] = get( key );
            }
        }
        if ( !is_uuid( this.rid ) ) { this.rid = uuid(); }
        if ( typeof this.locale !== 'string' || this.locale === '' ) { this.locale = 'en'; }
        set( 'rid', this.rid ); cookieSet( 'rid', this.rid, { path: '/' } );
        // 初始化系统信息
        this.refreshSystemInfo();
        this.cache['RefreshSystemInfoInterval'] = setInterval( this.refreshSystemInfo, 10000 );
        // 页面加载完成事件
        $( document ).ready( function() {
            // 注册点击复制
            Core.clipboard = new ClipboardJS( '.copy' );
            Core.clipboard.on( 'success', ( e ) => {
                Core.toast( 0, 'Success', `${t( 'base.copy' )}${t( 'base.true' )}` );
                e.clearSelection();
            });
            Core.clipboard.on( 'error', ( e ) => {
                Core.toast( 2, 'Error', `${t( 'base.copy' )}${t( 'base.false' )}` );
                console.error( e );
            });
        });
    },

    /**
     * 获取请求头
     * 返回包含当前请求标识和语言的 HTTP 请求头对象，若存在有效的 Bearer Token 则附加 Authorization。
     * @returns {object} HTTP 请求头对象
     */
    headers: function() {
        const headers = {
            'rid': this.rid,
            'locale': this.locale
        };
        const csrfToken = document.querySelector( 'meta[name="csrf-token"]' )?.content;
        if ( csrfToken ) { headers['X-CSRF-TOKEN'] = csrfToken; }
        const token = get( 'token' );
        if ( typeof token === 'string' && token !== '' ) { headers['Authorization'] = `Bearer ${token}`; }
        return headers;
    },

    /**
     * 刷新系统信息
     * 通过 Ajax 请求获取最新的系统信息并更新全局属性。
     * @param {string|null} langs 使用竖线分隔的语言包名称
     * @returns {void}
     */
    refreshSystemInfo: function( langs = null ) {
        if ( !Core.initialized ) {
            langs = `base${setting.langs && typeof setting.langs === 'string' && setting.langs !== '' ? `|${setting.langs}` : ''}`;
        }
        Core.web( `/api/index${typeof langs === 'string' && langs !== '' ? `?langs=${encodeURIComponent( langs )}` : ''}` )
            .success(( res ) => {
                // 更新系统信息
                Core.app = res.app || {}; set( 'app', Core.app );
                // 更新用户信息
                Core.user = res.user || null;
                if ( Core.user && typeof Core.user === 'object' ) {
                    set( 'user', Core.user );
                }else {
                    // 登录状态失效
                    Core.user = null; del( 'user' );
                    if ( get( 'token' ) ) {
                        del( 'token' );
                        location.reload();
                    }
                }
                // 更新语言包
                if ( res.lang && typeof res.lang === 'object' ) {
                    let saveLangs = {};
                    if ( !empty( res.lang.base ) ) { saveLangs['base'] = res.lang.base; }
                    set( 'langs', saveLangs );
                    Core.cache.lang = { ...Core.cache.lang, ...res.lang };
                }
                Core.initialized = true;
            })
            .request();
    },
    /**
     * 创建 Web 构建对象
     * @param {string} link 请求链接
     * @returns {webBuild} Web 构建对象
     */
    web: function( link ) { return new webBuild( link ); },
    /**
     * 创建弹窗对象
     * @param {string|jQuery} content 弹窗内容
     * @returns {object} 弹窗对象
     */
    popup: function( content ) {
        content = content instanceof jQuery ? content.html() : content;
        return {
            vRid: uuid(),
            vTitle: null,
            vIcon: null,
            vClose: true,
            vMask: true,
            vButtons: [],
            content: content,
            vWidth: '580px',
            vHeight: 'calc( var( --vh ) - 40px )',
            // 设置标题
            title: function( title ) { this.vTitle = title; return this; },
            // 设置标题图标
            icon: function( icon ) { this.vIcon = icon; return this; },
            // 设置是否显示关闭按钮
            close: function( close ) { this.vClose = !!close; return this; },
            // 设置按钮组
            buttons: function( buttons ) { this.vButtons = Array.isArray( buttons ) ? buttons : []; return this; },
            // 添加单个按钮
            button: function( button ) { if ( button && typeof button === 'object' ) { this.vButtons.push( button ); } return this; },
            // 设置弹窗宽度
            width: function( width ) { this.vWidth = width; return this; },
            // 设置弹窗高度
            height: function( height ) { this.vHeight = height; return this; },
            // 设置弹窗唯一标识
            rid: function( rid ) { this.vRid = rid; return this; },
            // 设置是否显示遮罩
            mask: function( mask ) { this.vMask = !!mask; return this; },
            // 显示弹窗
            show: function() {
                // 如果已存在相同 rid 的弹窗，则先关闭
                Core.popupClose( this.vRid );
                // 解析按钮功能
                for ( const key in this.vButtons ?? [] ) {
                    if ( typeof this.vButtons[key]['method'] === 'function' ) {
                        const methodName = `popupButtonMethod_${this.vRid}_${key}`;
                        Core.cache[methodName] = this.vButtons[key]['method'];
                        this.vButtons[key]['method'] = `Core.cache['${methodName}']( '${this.vRid}' )`;
                    }
                }
                $box = $( 'div#toview-unit-box div.toview-unit-popup' );
                $box.append( `
                    <div
                        class="toview-unit-popup-box center ${this.vMask ? 'active' : ''}"
                        rid="${this.vRid}"
                    >
                        <div class="toview-unit-popup-card" style="--width: ${this.vWidth}; --height: ${this.vHeight};">
                            ${this.vTitle !== null || this.vIcon !== null || this.vClose ? `
                                <div class="toview-unit-popup-header">
                                    ${this.vIcon !== null ? `<i class="bi block ${this.vIcon} toview-unit-popup-icon"></i>` : ''}
                                    ${this.vTitle !== null ? `<div class="toview-unit-popup-title more">${this.vTitle}</div>` : ''}
                                    ${this.vClose ? `<i class="bi block bi-x-lg r3 toview-unit-popup-close" onClick="Core.popupClose( '${this.vRid}' )"></i>` : ''}
                                </div>
                            ` : ''}
                            <div class="toview-unit-popup-content scroll">
                                ${content}
                            </div>
                            ${this.vButtons.length > 0 ? `
                                <div class="toview-unit-popup-footer toview-unit-popup-button${this.vButtons.length}">
                                    ${this.vButtons.map(( button ) => {return `
                                        <div onClick="${button.method ? button.method : ''}">${!empty( button.icon ) ? `<i class="bi ${button.icon}"></i> ` : ''}${button.title}</div>
                                    `}).join( '' )}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `);
                return this;
            }
        };
    },
    popupClose: function( rid ) {
        const $box = $( `div#toview-unit-box div.toview-unit-popup-box[rid="${rid}"]` );
        if ( $box.length ) {
            $box.remove();
        }
    },
    /**
     * 显示通知消息
     * @param {number} status 通知状态，0=成功、1=消息、2=错误、3=警告
     * @param {string} title 通知标题
     * @param {string} message 通知内容
     * @param {number} timeout 显示时长，单位毫秒，0 表示不自动关闭
     * @returns {void}
     */
    toast: function( status, title = '', message = '', timeout = 8000 ) {
        const closeToast = () => {
            clearTimeout( Core.cache['ToastTimeout'] );
            const $box = $( 'div#toview-unit-box div.toview-unit-toast-box' );
            $box.addClass( 'toview-unit-toast-box-close' ).one( 'animationend', function() {
                $box.removeClass( 'active' );
                $( this ).removeClass( 'toview-unit-toast-box-close' );
            });
        }
        if ( status === false ) {
            return closeToast();
        }
        // 数据整理
        const iconMap = {
            0: [ 'r4', 'bi-check-circle' ],
            1: [ 'r3', 'bi-info-circle' ],
            2: [ 'r5', 'bi-exclamation-circle' ],
            3: [ 'r5', 'bi-shield-x' ]
        };
        const icon = iconMap[parseInt( status )] || iconMap[0];
        title = typeof title === 'string' && title !== '' ? title.trim() : '';
        message = typeof message === 'string' && message !== '' ? message.trim() : '';
        timeout = parseInt( timeout ); if ( timeout < 0 ) { timeout = 0; }
        // 填充数据
        const $box = $( 'div#toview-unit-box div.toview-unit-toast-box' );
        $box.find( 'div.toview-unit-toast-item.toview-unit-toast-icon i' ).attr( 'class', `bi block ${icon[1]}` ).css( 'color', `rgb( var( --${icon[0]} ) )` );
        $box.find( 'div.toview-unit-toast-item.toview-unit-toast-text div.toview-unit-toast-text-title' ).text( title );
        $box.find( 'div.toview-unit-toast-item.toview-unit-toast-text div.toview-unit-toast-text-content' ).text( message );
        // 显示通知
        $box.addClass( 'active' );
        $box.addClass( 'toview-unit-toast-box-open' ).one( 'animationend', function() {
            $( this ).removeClass( 'toview-unit-toast-box-open' );
        });
        clearTimeout( Core.cache['ToastTimeout'] );
        if ( timeout > 0 ) {
            Core.cache['ToastTimeout'] = setTimeout( () => {
                closeToast();
            }, timeout );
        }
    },
    /**
     * 控制全屏加载状态
     * 显示或关闭页面全屏加载遮罩，并支持在指定时间后自动关闭。
     * @param {boolean} show 是否显示加载遮罩
     * @param {number} timeout 自动关闭时间，单位毫秒，0 表示不自动关闭
     * @returns {void}
     */
    loading: function( show = true, timeout = 0 ) {
        const closeLoading = () => {
            clearTimeout( Core.cache['LoadingTimeout'] );
            const $box = $( 'div#toview-unit-box div.toview-unit-loading-box' );
            $box.removeClass( 'active' );
        }
        if ( show ) {
            const $box = $( 'div#toview-unit-box div.toview-unit-loading-box' );
            $box.addClass( 'active' );
            if ( timeout > 0 ) {
                Core.cache['LoadingTimeout'] = setTimeout( () => {
                    closeLoading();
                }, timeout );
            }
        }else {
            closeLoading();
        }
    },
    /**
     * 控制元素加载状态
     * 在指定的 jQuery 元素内部显示或关闭加载遮罩，并支持在指定时间后自动关闭。
     * @param {jQuery} $card 需要显示加载遮罩的 jQuery 元素
     * @param {boolean} show 是否显示加载遮罩
     * @param {number} timeout 自动关闭时间，单位毫秒，0 表示不自动关闭
     * @returns {void}
     */
    boxLoading: function( $card, show = true, timeout = 0 ) {
        const closeBoxLoading = () => {
            $card.find( 'div.toview-unit-loading-box' ).removeClass( 'active' );
            setTimeout(() => {
                $card.find( 'div.toview-unit-loading-box' ).remove();
                $card.css({ 'overflow': '', 'position': '' });
            }, 300 );
        }
        const code = $box = $( 'div#toview-unit-box div.toview-unit-loading-box' ).html();
        if ( show ) {
            if ( $card.find( 'div.toview-unit-loading-box' ).length === 0 ) {
                $card.css({ 'overflow': 'hidden', 'position': 'relative' });
                $card.append( `
                    <div class="toview-unit-loading-box center" style="
                        width: 100%;
                        height: 100%;
                        background-color: rgb( var( --r6 ), 0.75 );
                        position: absolute;
                        top: 0;
                        left: 0;
                    ">${code}</div>
                ` );
            }
            setTimeout(() => {
                $card.find( 'div.toview-unit-loading-box' ).addClass( 'active' );
            }, 10 );
            if ( timeout > 0 ) {
                setTimeout( () => {
                    closeBoxLoading();
                }, timeout );
            }
        }else {
            closeBoxLoading();
        }
    },
    /**
     * 生成二维码
     * 在指定的 jQuery 元素内部生成二维码。
     * @param {jQuery} $box 需要生成二维码的 jQuery 元素
     * @param {string} text 二维码内容
     * @param {number} size 二维码尺寸，单位像素
     * @param {object} other 其他配置项
     * @returns {void}
     */
    QRCode: function( $box, text, size = 128, other = {} ) {
        $box.empty();
        $(() => {
            const qrcode = new QRCode( $box[0], {
                text: text,
                width: size,
                height: size,
                colorDark: other.color ?? `rgb( ${$( ':root' ).css( '--r1' ).trim()} )`,
                colorLight: other.background ?? 'rgba( 0, 0, 0, 0 )',
                correctLevel: QRCode.CorrectLevel.M,
                ...other
            });
        });
        setTimeout(() => { $box.removeAttr( 'title' ); }, 50 );
    },
    copy: async function( text ) {
        if ( typeof text !== 'string' || text === '' ) {
            Core.toast( 2, 'Error', `${t( 'base.copy' )}${t( 'base.false' )}` );
            return false;
        }
        try {
            if ( navigator.clipboard?.writeText && window.isSecureContext ) {
                await navigator.clipboard.writeText( text );
            }else {
                const $textarea = $( '<textarea>' ).val( text ).css({
                    position: 'fixed',
                    top: '-9999px',
                    left: '-9999px',
                    opacity: 0,
                });
                $( 'body' ).append( $textarea );
                $textarea[0].focus();
                $textarea[0].select();
                $textarea[0].setSelectionRange( 0, text.length );
                const copied = document.execCommand( 'copy' );
                $textarea.remove();
                if ( !copied ) {
                    throw new Error( '复制命令执行失败' );
                }
            }
            Core.toast( 0, 'Success', `${t( 'base.copy' )}${t( 'base.true' )}` );
            return true;
        }catch ( error ) {
            Core.toast( 2, 'Error', `${t( 'base.copy' )}${t( 'base.false' )}` );
            return false;
        }
    },
    /**
     * 获取表单数据
     * 获取表单内所有 ToView 输入组件的值，并按输入类型转换数据。
     * @param {HTMLFormElement} form 表单元素
     * @param {Function|null} method 回调函数，接收表单数据作为参数
     * @returns {object} 表单数据
     */
    submit: function( form, method = null ) {
        if ( !form || form.tagName !== 'FORM' ) { return false; }
        const data = {}; let status = true;
        const $form = $( form );
        const formatTime = ( value ) => {
            if ( !value ) { return null; }
            const time = value.split( ':' );
            return `${time[0]}:${time[1] ?? '00'}:${( time[2] ?? '00' ).slice( 0, 2 )}`;
        };
        $form.find( '[input]' ).each(( index, input ) => {
            const $input = $( input );
            const name = $input.attr( 'name' );
            const type = $input.closest( 'div.toview-input' ).attr( 'type' );
            if ( !name || !type || $input.prop( 'disabled' ) ) { return; }
            switch ( type ) {
                case 'select':
                    data[name] = $input.val();
                    break;
                case 'phone': {
                    const phoneCode = ( $input.closest( 'div.toview-input-phone' ).find( 'select[phone-code]' ).val() || '' ).replace( /\D/g, '' );
                    const phoneNumber = $input.val().replace( /\D/g, '' );
                    data[name] = phoneCode && phoneNumber ? `+${phoneCode} ${phoneNumber}` : null;
                    break;
                }
                case 'number':
                    data[name] = $input.val();
                    if ( data[name] !== null && data[name] !== '' ) {
                        data[name] = parseFloat( data[name] );
                    }
                    break;
                case 'switch':
                case 'agree':
                    data[name] = $input.prop( 'checked' );
                    break;
                case 'radio':
                    if ( !Object.prototype.hasOwnProperty.call( data, name ) ) { data[name] = null; }
                    if ( $input.prop( 'checked' ) ) { data[name] = $input.val(); }
                    break;
                case 'checkbox': {
                    const checkboxName = name.endsWith( '[]' ) ? name.slice( 0, -2 ) : name;
                    if ( !Object.prototype.hasOwnProperty.call( data, checkboxName ) ) { data[checkboxName] = []; }
                    if ( $input.prop( 'checked' ) ) { data[checkboxName].push( $input.val() ); }
                    break;
                }
                case 'datetime-local': {
                    const value = $input.val();
                    const datetime = value ? value.split( 'T' ) : [];
                    data[name] = value && datetime[1] ? `${datetime[0].replace( /-/g, '.' )} ${formatTime( datetime[1] )}` : null;
                    break;
                }
                case 'date':
                    data[name] = $input.val() ? $input.val().replace( /-/g, '.' ) : null;
                    break;
                case 'time':
                    data[name] = formatTime( $input.val() );
                    break;
                default:
                    data[name] = $input.val();
                    break;
            }
        });
        // 验证必填项
        $form.find( 'div.toview-input[required]' ).each(( index, inputBox ) => {
            if ( !status ) { return false; }
            const $inputBox = $( inputBox );
            const $input = $inputBox.find( '[input]' ).first();
            const type = $inputBox.attr( 'type' );
            const name = $input.attr( 'name' );
            if ( !$input.length || $input.prop( 'disabled' ) ) { return; }
            const dataName = type === 'checkbox' && name.endsWith( '[]' ) ? name.slice( 0, -2 ) : name;
            const value = data[dataName];
            if ( value !== undefined && value !== null && value !== '' && value !== false && ( !Array.isArray( value ) || value.length > 0 ) ) { return; }
            status = false;
            let title = $inputBox.find( 'div.toview-input-title span' ).text();
            title = typeof title === 'string' && title !== '' ? title.trim() : dataName;
            Core.toast( 2, t( 'base.error.s2' ), t( 'base.error.required', { attribute: title } ) );
            $inputBox.attr( 'error', '' );
            clearTimeout( Core.cache[`Form-${dataName}`] );
            Core.cache[`Form-${dataName}`] = setTimeout(() => { $inputBox.removeAttr( 'error' ); }, 3000 );
            return false;
        });
        if ( !status ) { return false; }
        if ( method && typeof method === 'function' ) { method( data ); }
        return data;
    }
};

/**
 * Web 请求构建器
 * 基于 jQuery Ajax 提供链式请求配置、加载状态、进度监听及统一响应处理。
 */
class webBuild {
    /**
     * 创建 Web 构建对象
     * @param {string} link 请求链接
     */
    constructor( link ) {
        this.config = { dataType: 'json' }; // 请求配置
        this.link = link; // 请求链接
        this.methodValue = 'GET'; // 请求方法
        this.dataValue = {}; // 请求数据
        this.headerValue = Core.headers(); // 请求头
        this.timeoutValue = 0; // 请求超时，单位毫秒，0 表示不限制
        this.successCallback = null; // 成功回调
        this.loadingCallback = null; // 加载回调
        this.errorCallback = null; // 错误回调
        this.downloadCallback = null; // 下载进度回调
        this.downloadTotal = 0; // 下载总字节数
        this.downloadPercent = 0; // 下载完成百分比
        this.uploadCallback = null; // 上传进度回调
        this.uploadTotal = 0; // 上传总字节数
        this.uploadPercent = 0; // 上传完成百分比
        this.autoCheck = true; // 是否自动检查
    }
    /**
     * 设置请求方法
     * 将有效的请求方法转换为大写并保存。
     * @param {string} method HTTP 请求方法
     * @returns {webBuild} 当前 Web 构建对象
     */
    method( method ) {
        if ( typeof method === 'string' && method !== '' ) {
            this.methodValue = method.toUpperCase();
        }
        return this;
    }
    /**
     * 设置请求数据
     * 普通对象与已有数据合并，FormData 等其他有效数据类型直接替换已有数据。
     * @param {object|FormData|string} data 请求数据
     * @returns {webBuild} 当前 Web 构建对象
     */
    data( data ) {
        if ( !empty( data ) ) {
            if ( Object.prototype.toString.call( data ) !== '[object Object]' ) {
                this.dataValue = data;
            }else {
                this.dataValue = { ...this.dataValue, ...data };
            }
        }
        return this;
    }
    /**
     * 设置请求头
     * 将传入的请求头合并到默认请求头中。
     * @param {object} header 请求头对象
     * @returns {webBuild} 当前 Web 构建对象
     */
    header( header ) {
        if ( Object.prototype.toString.call( header ) === '[object Object]' ) {
            this.headerValue = { ...this.headerValue, ...header };
        }
        return this;
    }
    /**
     * 设置请求超时时间
     * 设置 Ajax 请求的最大等待时间，0 表示不限制。
     * @param {number|string} timeout 超时时间，单位毫秒
     * @returns {webBuild} 当前 Web 构建对象
     */
    timeout( timeout ) {
        timeout = parseInt( timeout );
        if ( timeout >= 0 ) {
            this.timeoutValue = timeout;
        }
        return this;
    }
    /**
     * 设置成功回调
     * 请求成功并通过自动检查后执行回调。
     * @param {Function} callback 成功回调函数
     * @returns {webBuild} 当前 Web 构建对象
     */
    success( callback ) {
        if ( typeof callback === 'function' ) {
            this.successCallback = callback;
        }
        return this;
    }
    /**
     * 设置加载状态回调
     * 请求开始时传入 true，请求结束时传入 false。
     * @param {Function} callback 加载状态回调函数
     * @returns {webBuild} 当前 Web 构建对象
     */
    loading( callback ) {
        if ( typeof callback === 'function' ) {
            this.loadingCallback = callback;
        }
        return this;
    }
    /**
     * 设置错误回调
     * 请求失败且未被统一错误处理接管时执行回调。
     * @param {Function} callback 错误回调函数
     * @returns {webBuild} 当前 Web 构建对象
     */
    error( callback ) {
        if ( typeof callback === 'function' ) {
            this.errorCallback = callback;
        }
        return this;
    }
    /**
     * 设置下载进度回调
     * 下载过程中返回完成百分比、已下载字节数和总字节数。
     * @param {Function} callback 下载进度回调函数
     * @returns {webBuild} 当前 Web 构建对象
     */
    download( callback ) {
        if ( typeof callback === 'function' ) {
            this.downloadCallback = callback;
        }
        return this;
    }
    /**
     * 设置上传进度回调
     * 上传过程中返回完成百分比、已上传字节数和总字节数，并启用 FormData 请求配置。
     * @param {Function} callback 上传进度回调函数
     * @returns {webBuild} 当前 Web 构建对象
     */
    upload( callback ) {
        if ( typeof callback === 'function' ) {
            this.uploadCallback = callback;
            this.config = {
                ...this.config,
                processData: false,
                contentType: false
            };
        }
        return this;
    }
    /**
     * 设置自动响应检查状态
     * 控制请求完成后是否使用内置方法统一处理成功和错误响应。
     * @param {boolean} autoCheck 是否启用自动响应检查
     * @returns {webBuild} 当前 Web 构建对象
     */
    check( autoCheck ) {
        this.autoCheck = !!autoCheck;
        return this;
    }
    /**
     * 发起 Ajax 请求
     * 合并链式配置、上传下载监听和临时配置后执行请求。
     * @param {object} config 本次请求额外使用或覆盖的 jQuery Ajax 配置
     * @returns {jqXHR} jQuery Ajax 请求对象
     */
    request( config = {} ) {
        // 合并请求配置
        if ( Object.prototype.toString.call( config ) !== '[object Object]' ) {
            config = {};
        }
        let request = {
            url: this.link,
            method: this.methodValue,
            data: this.dataValue,
            headers: this.headerValue,
            timeout: this.timeoutValue,
            xhr: () => {
                const xhr = $.ajaxSettings.xhr();
                if ( xhr.upload && this.uploadCallback ) {
                    xhr.upload.addEventListener( 'progress', ( e ) => {
                        if ( e.lengthComputable ) {
                            const percent = Math.floor( e.loaded / e.total * 100 );
                            this.uploadPercent = percent;
                            this.uploadTotal = e.total;
                            this.uploadCallback( percent, e.loaded, e.total );
                        }
                    } );
                }
                if ( this.downloadCallback ) {
                    xhr.addEventListener( 'progress', ( e ) => {
                        if ( e.lengthComputable ) {
                            const percent = Math.floor( e.loaded / e.total * 100 );
                            this.downloadPercent = percent;
                            this.downloadTotal = e.total;
                            this.downloadCallback( percent, e.loaded, e.total );
                        }
                    } );
                }
                return xhr;
            },
            success: ( response ) => {
                if ( this.loadingCallback ) { this.loadingCallback( false ); }
                if ( this.uploadCallback && this.uploadPercent < 100 ) { this.uploadCallback( 100, this.uploadTotal, this.uploadTotal ); }
                if ( this.downloadCallback && this.downloadPercent < 100 ) { this.downloadCallback( 100, this.downloadTotal, this.downloadTotal ); }
                if ( this.autoCheck ) {
                    this.successSystem( response );
                    return;
                }
                if ( this.successCallback ) { this.successCallback( response ); }
            },
            error: ( ...args ) => {
                if ( this.loadingCallback ) { this.loadingCallback( false ); }
                if ( this.autoCheck ) {
                    this.errorSystem( ...args );
                    return;
                }
                if ( this.errorCallback ) { this.errorCallback( ...args ); }
            }
        };
        request = { ...request, ...this.config, ...config };
        // 执行请求
        if ( this.loadingCallback ) { this.loadingCallback( true ); }
        return $.ajax( request );
    }
    /**
     * 处理标准成功响应
     * 解析标准 JSON 响应，根据业务状态执行成功回调或显示通知。
     * @param {object|string} res 服务端响应内容
     * @returns {*} 回调或通知方法的返回值
     */
    successSystem( res ) {
        res = is_json( res ) ? JSON.parse( res ) : res;
        let toastCode = 2;
        if ( res && typeof res === 'object' && !empty( res.status ) ) {
            const text = typeof res.data === 'string' && res.data !== '' ? res.data : null;
            switch ( res.status ) {
                case 'success':
                    if ( this.successCallback ) { return this.successCallback( res.data ?? null ); }
                    if ( text ) { return Core.toast( 0, 'Success', text ); }
                    return;
                case 'info':
                    if ( text ) { return Core.toast( 1, t( 'base.error.s1' ), text ); }
                    toastCode = 1; break;
                case 'error':
                    if ( text ) { return Core.toast( 2, t( 'base.error.s2' ), text ); }
                    toastCode = 2; break;
                case 'warning':
                    if ( text ) { return Core.toast( 3, t( 'base.error.s3' ), text ); }
                    toastCode = 3; break;
                default: break;
            }
        }
        if ( this.errorCallback ) { this.errorCallback( res, null, null ); }
        return Core.toast( toastCode, t( 'base.error.s2' ), t( 'base.error.data' ) );
    }
    /**
     * 处理 Ajax 错误响应
     * 处理登录失效、可解析的标准 JSON 错误以及其他网络或服务器错误。
     * @param {jqXHR} xhr jQuery Ajax 请求对象
     * @param {string} textStatus 请求状态文本
     * @param {string} errorThrown HTTP 错误描述
     * @returns {*} 错误回调或通知方法的返回值
     */
    errorSystem( xhr, textStatus, errorThrown ) {
        const code = xhr.status;
        const response = xhr.responseText;
        // 登录状态失效
        if ( code === 401 ) {
            Core.user = null; del( 'user' );
            if ( get( 'token' ) ) {
                del( 'token' );
                Core.toast( 2, t( 'base.error.s2' ), t( 'base.error.401' ) );
                setTimeout( () => { location.reload(); }, 1000 );
                return;
            }
        }
        // 可能存在的可解析请求
        if ( is_json( response ) ) { return this.successSystem( response ); }
        // 其他错误
        if ( this.errorCallback ) { this.errorCallback( xhr, textStatus, errorThrown ); }
        return Core.toast( 2, t( 'base.error.s2' ), `${code}|${t( 'base.error.unknown' )}` );
    }
}

/**
 * 获取翻译文本
 * 使用点号键从已加载的语言包读取文本，并替换 Laravel 风格的占位符。
 * @param {string} key 翻译键名，例如 base.save 或 validation.required
 * @param {object} replacements 占位符替换参数
 * @returns {string} 翻译文本，无法获取时返回原键名
 */
function t( key, replacements = {} ) {
    if ( typeof key !== 'string' || key === '' ) { return ''; }
    if ( Object.prototype.toString.call( replacements ) !== '[object Object]' ) { replacements = {}; }
    const lang = Core.cache.lang;
    if ( !lang || Object.prototype.toString.call( lang ) !== '[object Object]' ) { return key; }
    const segments = key.split( '.' );
    if ( segments.some( ( segment ) => segment === '' || ['__proto__', 'prototype', 'constructor'].includes( segment ) ) ) {
        return key;
    }
    let translation = lang;
    for ( const segment of segments ) {
        if ( translation === null || typeof translation !== 'object' || !Object.prototype.hasOwnProperty.call( translation, segment ) ) {
            return key;
        }
        translation = translation[segment];
    }
    if ( typeof translation !== 'string' ) { return key; }
    for ( const [name, value] of Object.entries( replacements ) ) {
        if ( name === '' || !['string', 'number', 'boolean', 'bigint'].includes( typeof value ) ) { continue; }
        const content = String( value );
        const upperName = name.toUpperCase();
        const titleName = name.charAt( 0 ).toUpperCase() + name.slice( 1 );
        translation = translation.split( `:${upperName}` ).join( content.toUpperCase() );
        translation = translation.split( `:${titleName}` ).join( content.charAt( 0 ).toUpperCase() + content.slice( 1 ) );
        translation = translation.split( `:${name}` ).join( content );
    }
    return translation;
}

/**
 * 判断字符串是否为 JSON
 * 判断传入值是否为合法的 JSON 对象或 JSON 数组字符串。
 * @param {unknown} value 待判断的值
 * @returns {boolean} 是否为合法 JSON 字符串
 */
function is_json( value ) {
    if ( typeof value !== 'string' ) { return false; }
    const content = value.trim();
    if ( content === '' || !['{', '['].includes( content[0] ) ) { return false; }
    try {
        JSON.parse( content );
        return true;
    }catch ( error ) {
        return false;
    }
}

/**
 * 判断值是否为空
 * 支持空字符串、零值、布尔值、数组、普通对象、Map 及 Set。
 * @param {unknown} value 待判断的值
 * @returns {boolean} 是否为空
 */
function empty( value ) {
    if ( value === undefined || value === null || value === false ) { return true; }
    if ( value === 0 || value === 0n || value === '' || value === '0' ) { return true; }
    if ( Array.isArray( value ) ) { return value.length === 0; }
    if ( value instanceof Map || value instanceof Set ) { return value.size === 0; }
    if ( Object.prototype.toString.call( value ) === '[object Object]' ) {
        return Object.keys( value ).length === 0;
    }
    return false;
}

/**
 * 生成 UUID
 * 优先使用浏览器原生 UUID 方法，否则生成符合 RFC 4122 格式的 UUID v4。
 * @returns {string} UUID 字符串
 */
function uuid() {
    if ( typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function' ) {
        return crypto.randomUUID();
    }
    const bytes = new Uint8Array( 16 );
    if ( typeof crypto !== 'undefined' && typeof crypto.getRandomValues === 'function' ) {
        crypto.getRandomValues( bytes );
    }else {
        for ( let index = 0; index < bytes.length; index++ ) {
            bytes[index] = Math.floor( Math.random() * 256 );
        }
    }
    bytes[6] = ( bytes[6] & 0x0f ) | 0x40;
    bytes[8] = ( bytes[8] & 0x3f ) | 0x80;
    const value = Array.from( bytes, ( byte ) => byte.toString( 16 ).padStart( 2, '0' ) ).join( '' );
    return `${value.slice( 0, 8 )}-${value.slice( 8, 12 )}-${value.slice( 12, 16 )}-${value.slice( 16, 20 )}-${value.slice( 20 )}`;
}

/**
 * 判断字符串是否为 UUID
 * 判断传入值是否符合标准的 8-4-4-4-12 UUID 格式。
 * @param {unknown} value 待判断的值
 * @returns {boolean} 是否为 UUID 字符串
 */
function is_uuid( value ) {
    if ( typeof value !== 'string' ) { return false; }
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test( value );
}

/**
 * 获取本地存储数据
 * 读取并还原通过 set 保存的数据，存储不可用或键不存在时返回默认值。
 * @param {string} key 存储键名
 * @param {unknown} defaultValue 键不存在时返回的默认值
 * @returns {unknown} 已保存的数据或默认值
 */
function get( key, defaultValue = null ) {
    if ( typeof key !== 'string' || key === '' ) { return defaultValue; }
    let value = null;
    try {
        value = localStorage.getItem( key );
    }catch ( error ) {
        return defaultValue;
    }
    if ( value === null ) { return defaultValue; }
    const prefix = '__core_json__:';
    if ( value.startsWith( prefix ) ) {
        try {
            return JSON.parse( value.slice( prefix.length ) );
        }catch ( error ) {
            return defaultValue;
        }
    }
    // 兼容此前直接保存的 JSON 对象和数组。
    if ( is_json( value ) ) {
        try {
            return JSON.parse( value );
        }catch ( error ) {
            return defaultValue;
        }
    }
    return value;
}

/**
 * 保存本地存储数据
 * 字符串保持原值，其他可序列化值使用带标识的 JSON 保存。
 * @param {string} key 存储键名
 * @param {unknown} value 待保存的数据
 * @returns {boolean} 是否成功写入持久化存储
 */
function set( key, value ) {
    if ( typeof key !== 'string' || key === '' ) { return false; }
    if ( ['undefined', 'function', 'symbol'].includes( typeof value ) ) { return false; }
    let storageValue = value;
    try {
        if ( typeof value !== 'string' ) {
            storageValue = `__core_json__:${JSON.stringify( value )}`;
        }
    }catch ( error ) {
        return false;
    }
    try {
        localStorage.setItem( key, storageValue );
        return true;
    }catch ( error ) {
        return false;
    }
}

/**
 * 删除本地存储数据
 * 根据键名清除对应的持久化存储数据。
 * @param {string} key 存储键名
 * @returns {boolean} 是否成功从持久化存储删除
 */
function del( key ) {
    if ( typeof key !== 'string' || key === '' ) { return false; }
    try {
        localStorage.removeItem( key );
        return true;
    }catch ( error ) {
        return false;
    }
}

/**
 * 获取 Cookie 数据
 * 读取并还原通过 cookieSet 保存的数据，Cookie 不存在或读取失败时返回默认值。
 * @param {string} key Cookie 键名
 * @param {unknown} defaultValue Cookie 不存在时返回的默认值
 * @returns {unknown} 已保存的数据或默认值
 */
function cookieGet( key, defaultValue = null ) {
    if ( typeof key !== 'string' || key === '' ) { return defaultValue; }
    try {
        const value = $.cookie( key );
        if ( value === undefined ) { return defaultValue; }
        const prefix = '__core_json__:';
        if ( !value.startsWith( prefix ) ) { return value; }
        try {
            return JSON.parse( value.slice( prefix.length ) );
        }catch ( error ) {
            return defaultValue;
        }
    }catch ( error ) {
        return defaultValue;
    }
}

/**
 * 保存 Cookie 数据
 * 字符串保持原值，其他可序列化值使用带标识的 JSON 保存，默认作用于整个站点。
 * @param {string} key Cookie 键名
 * @param {unknown} value 待保存的数据
 * @param {object} options Cookie 选项，支持 expires、path、domain 和 secure
 * @returns {boolean} 是否成功写入 Cookie
 */
function cookieSet( key, value, options = {} ) {
    if ( typeof key !== 'string' || key === '' ) { return false; }
    if ( ['undefined', 'function', 'symbol'].includes( typeof value ) ) { return false; }
    if ( Object.prototype.toString.call( options ) !== '[object Object]' ) { return false; }
    let cookieValue = value;
    try {
        if ( typeof value !== 'string' ) {
            cookieValue = `__core_json__:${JSON.stringify( value )}`;
        }
        const cookieOptions = Object.assign( { path: '/' }, options );
        $.cookie( key, cookieValue, cookieOptions );
        return $.cookie( key ) === cookieValue;
    }catch ( error ) {
        return false;
    }
}

/**
 * 删除 Cookie 数据
 * 使用与写入时相同的 path 和 domain 删除 Cookie，默认作用路径为整个站点。
 * @param {string} key Cookie 键名
 * @param {object} options Cookie 选项，支持 path 和 domain
 * @returns {boolean} 是否成功删除 Cookie
 */
function cookieDel( key, options = {} ) {
    if ( typeof key !== 'string' || key === '' ) { return false; }
    if ( Object.prototype.toString.call( options ) !== '[object Object]' ) { return false; }
    try {
        const cookieOptions = Object.assign( { path: '/' }, options );
        if ( $.cookie( key ) === undefined ) { return true; }
        return $.removeCookie( key, cookieOptions );
    }catch ( error ) {
        return false;
    }
}

// 页面脚本加载完成后执行站点初始化。
Core.init();
