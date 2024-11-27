<div class="sticker">
    <div class="sticker-container">
        <div class="absolute-left logo" style="background-image:url({{ $veloLogo }})"></div>
        <div class="absolute-right courier-logo" style="background-image:url({{ $courierLogo }})"></div>

        <div class="sender text-left">
            <div class="bold sender-title">{{ $titles['senderTitle'] }}</div>
            <div>{{ $sender['name'] }}</div>
            <div>{{ $sender['phone'] }}</div>
            <div>{{ $sender['street'] }} {{ $sender['number'] }}, {{ $sender['city'] }}</div>
            <div class="line2">{{ $sender['line2'] }}</div>
        </div>

        <div class="separator1"></div>

        <div class="full-width text-center classification">{{ $classification }}</div>

        <div class="separator1"></div>

        <div class="receiver">
            <div class="bold receiver-title">{{ $titles['receiverTitle'] }}</div>
            <div class="bold receiver-name">{{ $receiver['name'] }}</div>
            <div>{{ $receiver['phone'] }}</div>
            <div>{{ $receiver['street'] }} {{ $receiver['number'] }}, {{ $receiver['city'] }}</div>
            <div class="line2">{{ $receiver['line2'] }}</div>
        </div>

        <div class="separator1"></div>

        <div class="attribute half timestamp">
            <div class="attribute-inner"><span class="bold">{{ $titles['timestamp'] }}:</span> {{ $timestamp->toDateString() }}</div>
        </div>
        <div class="attribute half line-number">
            <div class="attribute-inner">
                @if (strlen($lineNumber))
                    <span class="bold">{{ $titles['line_number'] }}:</span> {{ $lineNumber }}
                @else
                    <span class="bold">{{ (strlen($externalId)) ? $externalId : $titles['visit'] }}</span>
                @endif
            </div>
        </div>

        <div style="content: ''; display: table; clear: both;"></div>

        <div class="separator1"></div>


        <div class="barcode {{ strlen($barcodeId) > 10 ? 'long-barcode' : 'short-barcode' }} english">
            <img src="{{ $barcodeSrc }}" />
            <div class="text-center">
                <span class="bold">{{ $titles['order_number'] }}:</span> {{ $barcodeId }}
            </div>
        </div>
    </div>
</div>
