var root = this;
props = props || {};

function text(role, value) {
  if (value) root.querySelector('[data-role="' + role + '"]').textContent = value;
}

text('title', props.title);
text('subtitle', props.subtitle);

var primary = root.querySelector('[data-role="primary"]');
var secondary = root.querySelector('[data-role="secondary"]');

if (props.buttonUrl) primary.href = props.buttonUrl;
text('primary', props.buttonLabel);

if (props.secondaryLabel) {
  secondary.textContent = props.secondaryLabel;
  secondary.href = props.secondaryUrl || '#';
  secondary.classList.remove('hidden');
}

// With no button label set, the primary button follows the store mode ("Shop Products", "Enquire Products", "Browse Products").
if (!props.buttonLabel) {
  (window.storeQueue = window.storeQueue || []).push(function () {
    text('primary', window.Store.flags.hero_label);
  });
}
