<p data-paid-group-offer>A group costs {{ $n['groupPrice'] }} and includes {{ $n['groupCredits'] }} Normkredit after payment.
    @if($n['promotion']['active'])
        Get {{ number_format($n['promotion']['bonus_credits'], 2, '.', ',') }} bonus NC during the promotion, for {{ number_format($n['promotion']['total_credits'], 2, '.', ',') }} NC in total.
    @endif
    @if($n['freeSlots'] > 0)
        Your first {{ $n['freeSlots'] }} friends join free. Later members contribute once to join.
    @endif
</p>
