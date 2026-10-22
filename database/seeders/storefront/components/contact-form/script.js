var root = this;

// Posts to the CMS form API; the "contact" form and its field rules live under CMS → Forms.
var form = root.querySelector('[data-role="form"]');
var status = root.querySelector('[data-role="status"]');
var submit = root.querySelector('[data-role="submit"]');

function say(message, ok) {
  status.textContent = message;
  status.className = 'rounded-md px-4 py-3 text-sm ' + (ok ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700');
}

form.addEventListener('submit', function (event) {
  event.preventDefault();

  var data = {};
  new FormData(form).forEach(function (value, key) { data[key] = value; });

  submit.disabled = true;
  fetch('/api/cms/form-submissions', {
    method: 'POST',
    headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    body: JSON.stringify(data)
  })
    .then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (res.ok) {
          form.reset();
          say('Thanks! Your message has been sent.', true);
        } else {
          var first = json.errors && json.errors[Object.keys(json.errors)[0]];
          say(first ? first[0] : (json.message || 'Could not send your message.'), false);
        }
      });
    })
    .catch(function () { say('Could not send your message.', false); })
    .then(function () { submit.disabled = false; });
});
