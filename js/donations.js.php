function triggerPaypalDonation() {
  var form = $('<form action="https://www.paypal.com/donate" method="post" />');
  form.append($('<input type="hidden" name="business" value="Y7R8L3H36YNUE" />'));
  form.append($('<input type="hidden" name="no_recurring" value="1" />'));
  form.append($('<input type="hidden" name="item_name" value="Dancify" />'));
  form.append($('<input type="hidden" name="currency_code" value="SEK" />'));
  $(document.body).append(form);
  form.submit();
}

function triggerSwishDonation() {
  clearActionInputs();
  showActionInput('swish-qr');
  function clear(e) {
    clearActionInputs();
    $(document).unbind('mousedown', clear);
  }
  $(document).mousedown(clear);
}
