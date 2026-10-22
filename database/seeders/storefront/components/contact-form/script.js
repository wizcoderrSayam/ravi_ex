/*
 * The contact form, sent to the CMS form "contact" (CMS → Forms) at /api/cms/form-submissions,
 * its fields named contact_<field>. The server's rules decide what is required; their messages
 * show under each field.
 */
var root = this;
props = props || {};

Store.ready(function () {
  var form = root.querySelector('[data-role="form"]');
  var error = root.querySelector('[data-role="error"]');
  var submit = root.querySelector('[data-role="submit"]');
  if (props.heading) root.querySelector('[data-role="heading"]').textContent = props.heading;

  function clear() {
    error.hidden = true;
    form.querySelectorAll('.field-error').forEach(function (note) { note.remove(); });
    form.querySelectorAll('.is-invalid').forEach(function (field) { field.classList.remove('is-invalid'); });
  }

  function mark(name, message) {
    var field = form.elements[name];
    if (!field) return null;
    field.classList.add('is-invalid');
    var note = document.createElement('div');
    note.className = 'field-error';
    note.textContent = message;
    field.insertAdjacentElement('afterend', note);
    return field;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (Store.inEditor) return;
    clear();

    var first = null;
    Array.prototype.forEach.call(form.elements, function (field) {
      if (!field.name) return;
      var value = field.value.trim();
      var message = field.required && !value ? 'Please fill this in.' : (value && field.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? 'Please enter a valid email address.' : '');
      var marked = message ? mark(field.name, message) : null;
      first = first || marked;
    });
    if (first) {
      first.focus();
      return;
    }

    // Every field goes, empty ones too: the form's rules only check the fields a submission has.
    var body = {};
    Array.prototype.forEach.call(form.elements, function (field) {
      if (field.name) body[field.name] = field.value.trim();
    });
    submit.disabled = true;
    submit.textContent = 'Sending…';

    Store.request('POST', '/cms/form-submissions', body).then(function () {
      Store.swap(form, '<div class="cform-done" role="status"><i>✓</i><h3>Thank you, ji!</h3><p>Your message has reached us. We will get back to you soon.</p></div>');
    }, function (failure) {
      submit.disabled = false;
      submit.textContent = 'Send message';
      var fields = Object.keys(failure.errors || {}).filter(function (name) { return form.elements[name]; });
      fields.forEach(function (name) {
        var marked = mark(name, [].concat(failure.errors[name])[0]);
        first = first || marked;
      });
      if (first) {
        first.focus();
      } else {
        error.textContent = failure.message;
        error.hidden = false;
      }
    });
  });
});
