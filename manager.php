<?php

function newField($type, $name)
{
  $field = [];
  $field['type'] = $type;
  $field['name'] = $name;
  $field['title'] = $name;
  $field['editable'] = true;
  $field['visibleInList'] = true;
  $field['visibleInDetails'] = true;
  $field['mandatory'] = false;
  $field['allowHtml'] = false;
  $field['isTitle'] = false;
  $field['idParamName'] = 'itemId';
  return $field;
}


function newIdField()
{
  $field = newField('integer', 'id');
  $field['title'] = 'Nr.';
  $field['editable'] = false;
  $field['isTitle'] = true;
  return $field;
}


function newTextField($name, $title)
{
  $field = newField('text', $name);
  $field['title'] = $title;
  return $field;
}


function newTextAreaField($name, $title)
{
  $field = newField('textArea', $name);
  $field['title'] = $title;
  return $field;
}


function newBooleanField($name, $title)
{
  $field = newField('boolean', $name);
  $field['title'] = $title;
  return $field;
}


function newIntegerField($name, $title)
{
  $field = newField('integer', $name);
  $field['title'] = $title;
  return $field;
}


function newTimestampField($name, $title)
{
  $field = newField('timestamp', $name);
  $field['title'] = $title;
  $field['precision'] = 'minute';
  return $field;
}


function newForeignIdField($name, $title, $foreignPageName)
{
  $field = newField('foreignId', $name);
  $field['title'] = $title;
  $field['foreignPageName'] = $foreignPageName;
  $field['foreignItemName'] = null;
  return $field;
}


function newAction($type)
{
  $action = [];
  $action['type'] = $type;
  $action['perItem'] = false;
  $action['visibleInList'] = true;
  $action['visibleInDetails'] = true;
  $action['cssClass'] = null;
  return $action;
}


function newLinkAction($url, $title)
{
  $action = newAction('link');
  $action['url'] = $url;
  $action['title'] = $title;
  return $action;
}


function newLinkPerItemAction($url, $title, $idParamName = 'itemId')
{
  $action = newAction('link');
  $action['perItem'] = true;
  $action['url'] = $url;
  $action['title'] = $title;
  $action['idParamName'] = $idParamName;
  return $action;
}


function newAjaxPerItemAction($url, $title, $idParamName = 'itemId')
{
  $action = newAction('ajax');
  $action['perItem'] = true;
  $action['url'] = $url;
  $action['title'] = $title;
  $action['idParamName'] = $idParamName;
  $action['confirmText'] = null;
  return $action;
}


function renderItemTable($items, $fields, $actions = [])
{
  echo html_open('div', ['class' => 'itemList']);

  $topActions = [];
  $itemActions = [];
  foreach ($actions as $action)
  {
    if (!$action['visibleInList'])
      continue;
    if ($action['perItem'])
      $itemActions[] = $action;
    else
      $topActions[] = $action;
  }

  if (count($topActions) > 0)
  {
    echo html_open('div');
    foreach ($topActions as $action)
    {
      renderAction($action, false);
    }
    echo html_close('div');
  }

  if (count($items) == 0)
  {
    echo html_node('span', 'Keine Einträge vorhanden', ['class' => 'noItems']);
    return;
  }

  # Aktion 'Anzeigen'
  foreach ($fields as $field)
  {
    if ($field['isTitle'])
    {
      $action = newLinkPerItemAction('?p=' . get_param_value('p'), 'Anzeigen', $field['idParamName']);
      $action['visibleInDetails'] = false;
      $itemActions[] = $action;
      break;
    }
  }

  echo html_open('table', ['class' => 'border']);
  echo html_open('tr');
  foreach ($fields as $field)
  {
    if ($field['visibleInList'])
      echo html_node('th', html_encode($field['title']));
  }
  if (count($itemActions) > 0)
    echo html_node('th', 'Aktionen');
  echo html_close('tr');
  foreach ($items as $item)
  {
    echo html_open('tr', ['class' => array_value($item, 'class')]);
    foreach ($fields as $field)
    {
      if ($field['visibleInList'])
        renderField($field, $item, false, false);
    }
    if (count($itemActions) > 0)
    {
      echo html_open('td', ['class' => 'actions']);
      foreach ($itemActions as $action)
      {
        renderAction($action, true, $item);
      }
      echo html_close('td');
    }
    echo html_close('tr');
  }
  echo html_close('table');

  echo html_close('div');
}


function renderAction($action, $inItemList = false, $item = null)
{
  $titleEncoded = html_encode($action['title']);
  $attributes = [];
  $attributes['class'] = $action['cssClass'];
  $url = array_value($action, 'url');
  if ($action['perItem'] && $action['idParamName'] != null && $item != null)
    $url .= '&' . $action['idParamName'] . '=' . $item['id'];

  if ($action['type'] == 'link')
  {
    if ($inItemList)
      echo html_a($url, $titleEncoded, $attributes);
    else
      echo html_redirect_button($url, $titleEncoded, $attributes);
  }
  else if ($action['type'] == 'ajax')
  {
    $onSubmit = 'postForm(event);';
    $confirmText = $action['confirmText'];
    if ($confirmText != null)
      $onSubmit = 'if (!confirm("' . $confirmText . '")) return false;' . $onSubmit;
    echo html_open('form', ['action' => $url, 'onsubmit' => $onSubmit]);
    echo html_form_submit_button($titleEncoded, $attributes);
    writeFormToken();
    echo html_close('form');
  }
}


function renderItemDetails($creatingItem, $item, $fields, $actions = [], $saveActionName = null)
{
  $showFormScript = "event.target.parentNode.parentNode.parentNode.classList.add('editingFormOpened'); focusFirstChildInputNode(event.target.parentNode.parentNode.parentNode);";
  $hideFormScript = "event.target.parentNode.parentNode.parentNode.classList.remove('editingFormOpened');";

  $classes = [];
  $classes[] = 'itemDetails';
  if ($creatingItem)
    $classes[] = 'editingFormOpened';
  echo html_open('div', ['class' => implode(' ', $classes)]);

  # view
  if (!$creatingItem)
  {
    echo html_open('div', ['class' => 'view']);

    echo html_open('div');
    if ($saveActionName != null)
      echo html_button('Bearbeiten', ['onclick' => $showFormScript]);
    foreach ($actions as $action)
    {
      if (!$action['visibleInDetails'])
        continue;
      renderAction($action, false, $item);
    }
    echo html_close('div');

    renderFieldsTable($fields, $item, true);
    echo html_close('div');
  }

  # edit
  if ($saveActionName != null)
  {
    echo html_open('form', ['action' => '?a=' . $saveActionName, 'onsubmit' => 'postForm(event)']);
    renderFieldsTable($fields, $item, true, true, $creatingItem);

    echo html_open('div');
    echo html_form_submit_button('Speichern', ['class' => 'saveButton']);
    if (!$creatingItem)
      echo html_button('Abbrechen', ['class' => 'linkButton', 'onclick' => $hideFormScript]);
    echo html_close('div');

    writeFormToken();
    if ($creatingItem)
      echo html_node('script', 'focusFirstChildInputNode(document.body);');
    else
      echo html_input('hidden', 'itemId', $item['id']);
    echo html_close('form');
  }
  else if ($creatingItem)
    renderPageErrorBox('Ungültige Aktion.');

  echo html_close('div');
}


function renderFieldsTable($fields, $item, $itemDetails, $editForm = false, $creatingItem = false)
{
  echo html_open('table');
  foreach ($fields as $field)
  {
    if (!$field['visibleInDetails'])
      continue;
    if ($creatingItem && !$field['editable'])
      continue;
    $classes = [];
    if ($editForm && !$field['editable'])
      $classes[] = 'readOnly';
    echo html_open('tr', ['class' => implode(' ', $classes)]);
    echo html_open('td');
    echo html_encode($field['title']);
    if ($editForm && $field['mandatory'] && ($creatingItem || array_value($item, $field['name']) == null))
      echo html_node('span', '*', ['class' => 'mandatory', 'title' => 'erforderlich']);
    echo html_close('td');
    renderField($field, $item, $itemDetails, $editForm);
    echo html_close('tr');
  }
  echo html_close('table');
}


function renderField($field, $item, $itemDetails, $editForm = false)
{
  $fieldName = $field['name'];
  $value = array_value($item, $fieldName);
  $editing = $editForm && $field['editable'];

  if (!$editing && $value === null)
  {
    echo html_node('td', '–', ['class' => 'null']);
    return;
  }

  $type = $field['type'];
  $classes = [];
  $classes[] = 'preWrapped';
  $classes[] = $type;
  if ($type == 'timestamp')
    $classes[] = $field['precision'];
  $classes[] = $fieldName;
  echo html_open('td', ['class' => implode(' ', $classes)]);

  $showAsTitle = $field['isTitle'] && !$itemDetails;
  if ($showAsTitle)
    echo html_open('a', ['href' => '?p=' . get_param_value('p') . '&' . $field['idParamName'] . '=' . $item['id']]);

  if ($itemDetails)
    echo html_open('div', ['class' => 'textBlock']);

  if ($type == 'text')
  {
    if ($editing)
      echo html_input('text', $fieldName, $value);
    else
    {
      if ($field['allowHtml'])
        echo $value;
      else
        echo html_encode($value);
    }
  }
  else if ($type == 'textArea')
  {
    if ($editing)
      echo html_textarea($fieldName, $value);
    else
    {
      if ($field['allowHtml'])
        echo $value;
      else
        echo html_encode($value);
    }
  }
  else if ($type == 'boolean')
  {
    if ($editing)
      echo html_checkbox($fieldName, $value == 1);
    else
    {
      if ($value == 1)
        echo 'ja';
    }
  }
  else if ($type == 'integer')
  {
    if ($editing)
      echo html_input('number', $fieldName, $value);
    else
      echo html_encode($value);
  }
  else if ($type == 'timestamp')
  {
    $valueFormated = formatTimestampLocalShort($value, $field['precision']);
    if ($editing)
      echo html_input('text', $fieldName, $valueFormated);
    else
      echo $valueFormated;
  }
  else if ($type == 'foreignId')
  {
    $text = array_value($item, $fieldName . '_displayText');
    if ($text == null)
    {
      $text = $value;
      $foreignItemName = $field['foreignItemName'];
      if ($foreignItemName != null)
        $text = $foreignItemName . ' ' . $text;
    }
    echo html_a('?p=' . $field['foreignPageName'] . '&itemId=' . $value, html_encode($text));
  }

  if ($itemDetails)
    echo html_close('div');

  if ($showAsTitle)
    echo html_close('a');

  echo html_close('td');
}


function getSaveValues($fields)
{
  $values = [];
  foreach ($fields as $field)
  {
    if (!$field['editable'])
      continue;
    $fieldName = $field['name'];
    $title = $field['title'];
    $type = $field['type'];
    $value = trim(get_param_value($fieldName));
    if ($value === '')
      $value = null;

    if ($value == null && $field['mandatory'])
    {
      echo 'showErrorMsg("Bitte das Feld \'';
      echo $title;
      echo '\' ausfüllen.");';
      return null;
    }

    if ($value != null)
    {
      if ($type == 'boolean')
      {
        if ($value == 'true')
          $value = 1;
        else
          $value = null;
      }
      else if ($type == 'integer')
      {
        if (!is_numeric($value) || intval($value) != $value)
        {
          echo 'showErrorMsg("Bitte eine Ganzzahl im Feld \'';
          echo $title;
          echo '\' eingeben.");';
          return null;
        }
        $value = intval($value);
      }
      else if ($type == 'timestamp')
      {
        $timestamp = date_time_to_timestamp($value);
        if (!$timestamp)
        {
          echo 'showErrorMsg("Bitte ein Datum im Feld \'';
          echo $title;
          echo '\' eingeben.");';
          return null;
        }
        $value = format_timestamp($timestamp);
      }
    }

    $values[$fieldName] = $value;
  }
  return $values;
}
