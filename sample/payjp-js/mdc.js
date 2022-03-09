// Material Component Webの初期化
function setUpMDC() {
  if (!mdc) {
    return null
  }
  mdc.ripple.MDCRipple.attachTo(document.querySelector('form'));
  document.querySelectorAll('.mdc-text-field').forEach(function (e) {
    new mdc.textField.MDCTextField(e);
  })
  buttonRipple = new mdc.ripple.MDCRipple(document.querySelector('.mdc-button'))
  return buttonRipple.root
}
