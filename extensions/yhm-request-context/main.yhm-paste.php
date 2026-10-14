<?php

class YhmPasteUI implements iApplicationUIExtension
{
	public function OnDisplayProperties($oObject, \Combodo\iTop\Application\WebPage\WebPage $oPage, $bEditMode = false)
	{
		if (!($oObject instanceof UserRequest) && !($oObject instanceof Incident)) {
			return;
		}
		$bOfferButton = $bEditMode && $oObject->IsNew();
		$class = get_class($oObject);
		$url = addslashes(utils::GetAbsoluteUrlAppRoot().'pages/yhm-paste.php');
		$sOffer = $bOfferButton ? 'true' : 'false';
		$oPage->add_ready_script(<<<JS
(function () {
  if (!document.getElementById('yhm-paste-style')) {
    var style = document.createElement('style');
    style.id = 'yhm-paste-style';
    style.textContent = 'details.yhm-paste{margin-top:1.25rem} details.yhm-paste summary{cursor:pointer;font-weight:600;color:#0f4c81}';
    document.head.appendChild(style);
  }
  var node = document.querySelector('[data-attribute-code="yhm_paste"]');
  if (!node || node.closest('details.yhm-paste')) {
    return;
  }
  var section = node.closest('fieldset') || node;
  var form = section.closest('form') || section.parentElement;
  var details = document.createElement('details');
  details.className = 'yhm-paste';
  var summary = document.createElement('summary');
  summary.textContent = 'Paste an email';
  details.appendChild(summary);
  details.appendChild(section);
  var legend = section.querySelector ? section.querySelector('legend') : null;
  if (legend) {
    legend.style.display = 'none';
  }
  form.appendChild(details);
  var label = node.querySelector('.ibo-field--label, .form_field label');
  if (label) {
    label.style.display = 'none';
  }
  if (!$sOffer) {
    return;
  }
  var field = document.querySelector('[name="attr_yhm_paste"], textarea[name$="[yhm_paste]"]');
  var box = field ? field.parentElement : node;
  if (box && !document.getElementById('yhm-read-email')) {
    var model = document.createElement('select');
    model.id = 'yhm-model';
    model.style.marginTop = '0.5rem';
    model.style.marginRight = '0.5rem';
    [['qwen3.5:2b', 'Fast model'], ['granite4.2:8b', 'Stronger model, slower']].forEach(function (pair) {
      var option = document.createElement('option');
      option.value = pair[0];
      option.textContent = pair[1];
      model.appendChild(option);
    });
    var button = document.createElement('button');
    button.type = 'button';
    button.id = 'yhm-read-email';
    button.textContent = 'Make the tickets';
    button.style.marginTop = '0.5rem';
    var status = document.createElement('span');
    status.id = 'yhm-read-status';
    status.style.marginLeft = '0.6rem';
    box.appendChild(model);
    box.appendChild(button);
    box.appendChild(status);
    button.addEventListener('click', function (event) {
      event.preventDefault();
      var text = field ? field.value : '';
      status.textContent = 'Reading with ' + model.value + '…';
      button.disabled = true;
      var body = new URLSearchParams();
      body.set('text', text);
      body.set('class', '$class');
      body.set('model', model.value);
      var control = new AbortController();
      var timer = setTimeout(function () { control.abort(); }, model.value.indexOf('granite') === 0 ? 90000 : 25000);
      fetch('$url', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body.toString(), signal: control.signal})
        .then(function (response) { clearTimeout(timer); return response.json(); })
        .then(function (data) {
          if (!data.ok || !data.tickets || !data.tickets.length) {
            status.textContent = data.message || 'Could not make the tickets.';
            button.disabled = false;
            return;
          }
          if (data.tickets.length === 1) {
            window.location.href = data.tickets[0].url;
            return;
          }
          status.textContent = '';
          var list = document.createElement('div');
          data.tickets.forEach(function (ticket) {
            var line = document.createElement('div');
            var link = document.createElement('a');
            link.href = ticket.url;
            link.textContent = ticket.ref + ' — ' + ticket.title;
            line.appendChild(link);
            list.appendChild(line);
          });
          status.appendChild(list);
          button.disabled = false;
        })
        .catch(function () {
          clearTimeout(timer);
          status.textContent = 'The model did not finish. Use the fast model, or paste a shorter message.';
          button.disabled = false;
        });
    });
  }
})();
JS
		);
	}

	public function OnDisplayRelations($oObject, \Combodo\iTop\Application\WebPage\WebPage $oPage, $bEditMode = false)
	{
	}

	public function OnFormSubmit($oObject, $sFormPrefix = '')
	{
	}

	public function OnFormCancel($sTempId)
	{
	}

	public function EnumUsedAttributes($oObject)
	{
		return [];
	}

	public function GetIcon($oObject)
	{
		return '';
	}

	public function GetHilightClass($oObject)
	{
		return HILIGHT_CLASS_NONE;
	}

	public function EnumAllowedActions(DBObjectSet $oSet)
	{
		return [];
	}
}
