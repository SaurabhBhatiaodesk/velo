<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Velo Playground!</title>
        <script>
            !function(o,d,s,e,f){var i,a,p,c=[],h=[];function t(){var t="You must provide a supported major version.";try{if(!f)throw new Error(t);var e,n="https://cdn.smooch.io/",r="smooch";e="string"==typeof this.response?JSON.parse(this.response):this.response;var o=f.match(/([0-9]+)\.?([0-9]+)?\.?([0-9]+)?/),s=o&&o[1],i=o&&o[2],a=o&&o[3],p=e["v"+s],c=e["v"+s+"."+i+".patch"];if(e.url||p||c){var h=d.getElementsByTagName("script")[0],u=d.createElement("script");if(u.async=!0,a)u.src=c||n+r+"."+f+".min.js";else{if(!(5<=s&&p))throw new Error(t);u.src=p}h.parentNode.insertBefore(u,h)}}catch(e){e.message===t&&console.error(e)}}o[s]={init:function(){i=arguments;var t={then:function(e){return h.push({type:"t",next:e}),t},catch:function(e){return h.push({type:"c",next:e}),t}};return t},on:function(){c.push(arguments)},render:function(){a=arguments},destroy:function(){p=arguments}},o._onWebMessengerHostReady=function(e){if(delete o.onWebMessengerHostReady_,o[s]=e,i)for(var t=e.init.apply(e,i),n=0;n<h.length;n++){var r=h[n];t="t"===r.type?t.then(r.next):t.catch(r.next)}a&&e.render.apply(e,a),p&&e.destroy.apply(e,p);for(n=0;n<c.length;n++)e.on.apply(e,c[n])};var n=new XMLHttpRequest;n.addEventListener("load",t),n.open("GET","https://"+e+".webloader.smooch.io/",!0),n.responseType="json",n.send()}(window,document,"Smooch","6696599f0f9da8d69bacc6e5","5");
        </script>
    </head>

<body>
    <h2>Playground</h2>
    <iframe id="velo-venti-iframe" src="{{ $appUrl }}/venti/eQy0j69bslg7lRfngy4L" style="width:100%;height:100%;min-width:1050px;min-height:800px;border-width:0;"></iframe>
    <script>
        setTimeout(()=>{
            const delegate = {
                beforeDisplay(message, data) {

                    if(message.role==='business' ) {
//                        message.text = '';
                    }
                    return message;
                }
            };
            Smooch.init({
                integrationId: '6696599f0f9da8d69bacc6e5',
                settings: {
                    // Specify the language settings
                    locale: 'nl' // Example: 'es' for Spanish, 'fr' for French
                },
                customColors: {
                    brandColor: '0b60ff',
                    conversationColor: 'ff0b60',
                    actionColor: '0b60ff',
                },
                displayStyle: 'tab',
                businessName: 'Velo Support',
                businessIconUrl: 'https://velo7371.zendesk.com/embeddable/avatars/24166968042513',
                customText: {
                    headerText: 'איך אפשר לעזור?',
                    inputPlaceholder: 'שלח הודעה...',
                    sendButtonText: 'שלח'
                },
                delegate

            }).then(
                function() {
                    var jwtToken = '{{$jwt}}';
                    var externalId = 'velo-qa';
                    Smooch.login(externalId, jwtToken).then(() => {
                        console.log('User logged in successfully');
                        // Additional actions after login if needed
                    }).catch((err) => {
                        console.error('Error logging in user:', err);
                    });

                },
                function(err) {
                    // Something went wrong during initialization
                }
            );

        },1000);
    </script>

</body>
</html>
