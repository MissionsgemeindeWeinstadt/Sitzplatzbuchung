<?php

function decodeBooking(&$booking, $withEventTitleAndDate = false)
# Convert all timestamp fields from strings to timestamps
{
  if ($booking != null)
  {
    $booking['insertTimestamp'] = date_time_to_timestamp($booking['insertTimestamp']);
    $booking['cancelTimestamp'] = date_time_to_timestamp($booking['cancelTimestamp']);

    $personsHtml = '';
    $persons = explode(';', $booking['listOfPersons']);
    if (count($persons) > 1)
      $personsHtml .= html_open('ol');
    foreach ($persons as $personStr)
    {
      if (count($persons) > 1)
        $personsHtml .= html_open('li');
      $person = explode(',', $personStr);
      $surname = array_value($person, 0);
      $lastname = array_value($person, 1);

      $personsHtml .= html_encode($surname);
      $personsHtml .= ' ';
      $personsHtml .= html_encode($lastname);
      if (count($persons) > 1)
        $personsHtml .= html_close('li');
    }
    if (count($persons) > 1)
      $personsHtml .= html_close('ol');
    $booking['listOfPersonsHtml'] = $personsHtml;

    if ($withEventTitleAndDate)
    {
      $eventId = $booking['eventId'];
      if ($eventId != null)
      {
        $event = tryGetEventById($eventId);
        $booking['eventId_displayText'] = $event['titleAndDate'];
      }
    }
  }
}


function getBookingBaseQuery()
{
  $sql = 'SELECT *';
  $sql .= ', (SELECT userName FROM client WHERE booking.insertClientId = client.id) AS insertClientId_displayText';
  $sql .= ', (SELECT userName FROM client WHERE booking.cancelClientId = client.id) AS cancelClientId_displayText';
  $sql .= ' FROM booking';
  return $sql;
}


function tryGetBookingById($itemId, $withEventTitleAndDate = false)
{
  if ($itemId == null)
    return;
  $sql = getBookingBaseQuery();
  $sql .= ' WHERE id = ?';
  $item = db()->try_query_row($sql, [$itemId]);
  decodeBooking($item, $withEventTitleAndDate);
  return $item;
}


function getCurrentBookingByEventForClient()
{
  $bookings = db()->query_rows('SELECT * FROM booking WHERE insertClientId = ? AND insertedAsAdmin IS NULL ORDER BY id DESC LIMIT 100', [getClientValue('id')]);
  $bookings = array_reverse($bookings);
  $result = [];
  foreach ($bookings as $booking)
  {
    $result[$booking['eventId']] = $booking;
  }
  return $result;
}


function getActiveBookingForEventForClient($eventId)
{
  return db()->try_query_row('SELECT * FROM booking WHERE insertClientId = ? AND insertedAsAdmin IS NULL AND eventId = ? AND cancelTimestamp IS NULL', [getClientValue('id'), $eventId]);
}


function getAdminBookings($eventId)
{
  $sql = getBookingBaseQuery();
  $sql .= ' WHERE eventId = ?';
  $sql .= ' ORDER BY id DESC';
  $sql .= ' LIMIT 100';

  $items = db()->query_rows($sql, [$eventId]);

  foreach ($items as &$item)
  {
    decodeBooking($item);
  }

  return $items;
}


function handleSaveBookingAction()
# Parameters:
# - eventId
# - asAdmin (optional)
# - surname1..surname6
# - lastname1..lastname6
# - phoneNumber
# - addressLine1
# - addressLine2
{
  $eventId = get_param_value('eventId');
  $event = tryGetEventById($eventId);
  if ($event == null)
  {
    echo 'showErrorMsg("Datensatz existiert nicht.");';
    echo 'location.reload();';
    return;
  }

  $asAdmin = isClientAdmin() && get_param_value('asAdmin') == 1;

  if (!$asAdmin && !isBookingOpen($event))
  {
    echo 'showErrorMsg("Der Buchungszeitraum ist abgelaufen.");';
    echo 'location.reload();';
    return;
  }

  if (!$asAdmin && getActiveBookingForEventForClient($eventId) != null)
  {
    echo 'showErrorMsg("Die Veranstaltung wurde bereits von diesem Gerät gebucht.");';
    echo 'location.reload();';
    return;
  }

  $persons = [];
  for ($i = 1; $i <= 6; $i++)
  {
    $surname = clean_whitespaces(get_param_value('surname' . $i));
    $lastname = clean_whitespaces(get_param_value('lastname' . $i));
    if ($surname == '' && $lastname == '')
      continue;
    if (strlen($surname) == '')
    {
      echo 'showErrorMsg("Bitte den Vornamen aller Personen angeben.");';
      return;
    }
    if (strlen($lastname) == '')
    {
      echo 'showErrorMsg("Bitte den Nachnamen aller Personen angeben.");';
      return;
    }
    if (strlen($surname) < 3)
    {
      echo 'showErrorMsg("Vorname ist zu kurz.");';
      return;
    }
    if (strlen($lastname) < 3)
    {
      echo 'showErrorMsg("Nachname ist zu kurz.");';
      return;
    }
    if (contains($surname, ',') or contains($lastname, ','))
    {
      echo 'showErrorMsg("Der Name darf kein Komma enthalten.");';
      return;
    }
    if (contains($surname, ';') or contains($lastname, ';'))
    {
      echo 'showErrorMsg("Der Name darf kein Semikolon enthalten.");';
      return;
    }
    if (contains($surname, '|') or contains($lastname, '|'))
    {
      echo 'showErrorMsg("Der Name darf keinen senkrechten Strich enthalten.");';
      return;
    }
    $person = $surname . ',' . $lastname;
    if (in_array($person, $persons))
    {
      echo 'showErrorMsg("Ein Name wurde doppelt eingegeben.");';
      return;
    }
    $persons[] = $person;
  }

  if (count($persons) == 0)
  {
    echo 'showErrorMsg("Bitte einen Namen eingeben.");';
    return;
  }

  $phoneNumber = clean_whitespaces(get_param_value('phoneNumber'));
  if (getConfigValue('requestPhoneNumber'))
  {
    if ($phoneNumber == '')
    {
      echo 'showErrorMsg("Bitte eine Telefonnummer eingeben.");';
      return;
    }
    if (strlen($phoneNumber) < 7)
    {
      echo 'showErrorMsg("Telefonnummer ist zu kurz.");';
      return;
    }
  }

  $addressLine1 = clean_whitespaces(get_param_value('addressLine1'));
  $addressLine2 = clean_whitespaces(get_param_value('addressLine2'));
  if (getConfigValue('requestAddress'))
  {
    if ($addressLine1 == '')
    {
      echo 'showErrorMsg("Bitte Straße und Hausnummer eingeben.");';
      return;
    }
    if (strlen($addressLine1) < 5)
    {
      echo 'showErrorMsg("Straße und Hausnummer ist zu kurz.");';
      return;
    }
    if ($addressLine2 == '')
    {
      echo 'showErrorMsg("Bitte PLZ und Ort eingeben.");';
      return;
    }
    if (strlen($addressLine2) < 9)
    {
      echo 'showErrorMsg("PLZ und Ort ist zu kurz.");';
      return;
    }
  }

  $listOfPersons = implode(';', $persons);

  # update client row
  if (!$asAdmin)
  {
    $personGroups = explode('|', getClientValue('lastListOfPersons'), 2);
    if ($event['isKidsEvent'] == 1)
    {
      if (count($personGroups) < 2)
        $personGroups[] = '';
      $personGroups[1] = $listOfPersons;
    }
    else
    {
      $personGroups[0] = $listOfPersons;
    }
    $clientValues = [];
    $clientValues['lastListOfPersons'] = implode('|', $personGroups);
    $clientValues['lastPhoneNumber'] = $phoneNumber;
    $clientValues['lastAddressLine1'] = $addressLine1;
    $clientValues['lastAddressLine2'] = $addressLine2;
    $clientValues['persistent'] = 1;
    db()->try_update_by_id('client', getClientValue('id'), $clientValues);
  }

  # check free seat count
  $freeSeatCount = calculateFreeSeatCount($event, count($persons));
  if ($freeSeatCount == -1)
  {
    $freeSeatCountWithoutNew = calculateFreeSeatCount($event);
    if ($freeSeatCountWithoutNew > 0 && count($persons) > 1)
      echo 'showErrorMsg("Es sind nicht mehr genügend Plätze frei. Bitte weniger Personen eingeben.");';
    else
      echo 'showErrorMsg("Es sind keine Plätze mehr frei.");';
    echo 'location.reload();';
    return;
  }

  # insert booking row
  $booking = [];
  $booking['eventId'] = $eventId;
  $booking['listOfPersons'] = $listOfPersons;
  $booking['personCount'] = count($persons);
  $booking['phoneNumber'] = $phoneNumber;
  $booking['addressLine1'] = $addressLine1;
  $booking['addressLine2'] = $addressLine2;
  $booking['insertTimestamp'] = format_timestamp(time());
  $booking['insertClientId'] = getClientValue('id');
  if ($asAdmin)
    $booking['insertedAsAdmin'] = 1;
  $bookingId = db()->insert('booking', $booking);

  # insert adminlog entry
  if ($asAdmin)
    addAdminlogEntry('booking', $bookingId, 'create', $booking);

  if ($asAdmin)
    echo js_redirect('?p=bookings&eventId=' . $eventId);
  else
    echo 'location.reload();';
}


function handleCancelBookingAction()
# Parameters:
# - eventId or
# - itemId (as admin)
{
  $itemId = get_param_value('itemId');
  $booking = null;
  $asAdmin = $itemId != null && isClientAdmin();
  if ($asAdmin)
  {
    $booking = tryGetBookingById($itemId);
    if ($booking == null)
    {
      echo 'showErrorMsg("Datensatz existiert nicht.");';
      echo 'location.reload();';
      return;
    }
  }
  else
  {
    $eventId = get_param_value('eventId');
    $event = tryGetEventById($eventId);
    if ($event == null)
    {
      echo 'showErrorMsg("Veranstaltung existiert nicht.");';
      echo 'location.reload();';
      return;
    }

    $booking = getActiveBookingForEventForClient($eventId);
    if ($booking == null)
    {
      echo 'showErrorMsg("Keine Buchung gefunden.");';
      echo 'location.reload();';
      return;
    }

    if (!isBookingOpen($event))
    {
      echo 'showErrorMsg("Der Buchungszeitraum ist abgelaufen.");';
      echo 'location.reload();';
      return;
    }
  }

  if ($booking['cancelTimestamp'] != null)
  {
    echo 'showErrorMsg("Die Buchung ist bereits storniert.");';
    echo 'location.reload();';
    return;
  }

  # update booking row
  $values = [];
  $values['cancelTimestamp'] = format_timestamp(time());
  $values['cancelClientId'] = getClientValue('id');
  db()->update_by_id('booking', $booking['id'], $values);

  # insert adminlog entry
  if ($asAdmin)
    addAdminlogEntry('booking', $booking['id'], 'cancel');

  echo 'location.reload();';
}


function renderVisitorsSheet()
{
  $eventId = get_param_value('eventId');
  if ($eventId == null)
    renderVisitorsSheetList();
  else
    renderVisitorsSheetDetails($eventId);
}


function renderVisitorsSheetList()
{
  if (!isClientAdmin())
  {
    renderForbiddenError();
    return;
  }
  writeMainHtmlBeforeContent('Anwesenheitslisten');

  echo html_open('div', ['class' => 'content visitorList']);

  $fields = [];

  renderItemTable(getVisitorListEvents(), getEventFieldsForVisitorList());

  echo html_close('div');
}


function renderVisitorsSheetDetails($eventId)
{
  if (!isClientAdmin())
  {
    renderForbiddenError();
    return;
  }

  $event = tryGetEventById($eventId);
  if ($event == null)
  {
    renderNotFoundError();
    return;
  }

  writeMainHtmlBeforeContent('Anwesenheitsliste ' . $event['titleAndDate']);

  echo html_open('div', ['class' => 'content visitorList']);

  $rows = renderVisitorsSheetDetails_getRealRows($eventId);
  $visitorCount = count($rows);
  if ($visitorCount > 0)
    renderVisitorsSheetDetails_addNumberingAndEmptyRows($rows);

  $fields = [];

  $field = newIdField();
  $field['isTitle'] = false;
  $fields[] = $field;

  $field = newTextField('name', 'Name');
  $fields[] = $field;

  $field = newTextField('empty', 'Anwes.');
  $fields[] = $field;

  if (getConfigValue('requestPhoneNumber') || getConfigValue('requestAddress'))
  {
    $text = '';
    if (getConfigValue('requestAddress'))
    {
      $text .= 'Telefon';
    }
    if (getConfigValue('requestAddress'))
    {
      if ($text != '')
      {
        $text .= ' und ';
      }
      $text .= 'Adresse';
    }
    $field = newTextField('phoneNumberAndAddress', $text);
    $fields[] = $field;
  }

  $field = newTextField('bookingInfo', 'Buchung');
  $fields[] = $field;

  echo html_button('Drucken', ['onclick' => 'window.print();']);

  echo html_open('div', ['class' => 'subTitle']);
  echo $visitorCount;
  echo ' Teilnehmer gebucht';
  echo ', Buchung ';
  if (time() > $event['bookingClosingTimestamp'])
    echo 'beendet';
  else
  {
    echo 'noch offen bis ';
    echo formatTimestampLocalLong($event['bookingClosingTimestamp'], 'minute', false);
  }
  echo html_close('div');

  echo html_open('div', ['class' => 'subTitle currentDate']);
  echo 'Stand: ';
  echo formatTimestampLocalLong(time(), 'minute');
  echo html_close('div');

  renderItemTable($rows, $fields);

  echo html_close('div');
}


function renderVisitorsSheetDetails_getRealRows($eventId)
{
  $bookings = db()->query_rows('SELECT * FROM booking WHERE eventId = ? AND cancelTimestamp IS NULL', [$eventId]);

  $rows = [];
  foreach($bookings as $booking)
  {
    $persons = explode(';', $booking['listOfPersons']);
    if (count($persons) == 1)
      $bookingInfo = count($persons) . ' Person';
    else
      $bookingInfo = count($persons) . ' Pers., #' . $booking['id'];
    foreach($persons as $personStr)
    {
      $person = explode(',', $personStr);
      $surname = array_value($person, 0);
      $lastname = array_value($person, 1);

      $row = [];
      $row['bookingInfo'] =  $bookingInfo;

      $phoneNumberAndAddress = $booking['phoneNumber'];
      if ($booking['addressLine1'] != '')
      {
        if ($phoneNumberAndAddress != '')
        {
          $phoneNumberAndAddress .= ', ';
        }
        $phoneNumberAndAddress .= $booking['addressLine1'] . ', ' . $booking['addressLine2'];
      }
      $row['phoneNumberAndAddress'] = $phoneNumberAndAddress;

      $row['name'] = $lastname . ', ' . $surname;
      $row['empty'] = '';
      $key = implode(' ', [$lastname, $surname, $booking['id']]);
      $rows[$key] = $row;
    }
  }

  ksort($rows);

  return $rows;
}


function renderVisitorsSheetDetails_addNumberingAndEmptyRows(&$rows)
{
  $i = 1;
  foreach($rows as &$row)
  {
    $row['id'] = $i;
    $i++;
  }
  unset($row);

  $emptyRowCount = 15;
  for($j = 0; $j < $emptyRowCount; $j++)
  {
    $row = [];
    $row['id'] = $i;
    $row['bookingInfo'] = null;
    $row['phoneNumberAndAddress'] = '';
    $row['name'] = '';
    $row['empty'] = '';
    $row['class'] = 'empty';
    $rows[] = $row;
    $i++;
  }
}


function renderBookings()
{
  if (!isClientAdmin())
  {
    renderForbiddenError();
    return;
  }

  $itemId = get_param_value('itemId');
  if ($itemId == null)
  {
    $eventId = get_param_value('eventId');
    if ($eventId == null)
      renderBookingListEvents();
    else
      renderBookingList($eventId);
  }
  else
    renderBookingDetails($itemId);
}


function renderBookingListEvents()
{
  writeMainHtmlBeforeContent('Buchungen verwalten');

  echo html_open('div', ['class' => 'content']);

  renderItemTable(getAdminEvents(), getEventFieldsForVisitorList());

  echo html_close('div');
}


function renderBookingList($eventId)
{
  $event = tryGetEventById($eventId, true, true);
  if ($event == null)
  {
    renderNotFoundError();
    return;
  }

  writeMainHtmlBeforeContent('Buchungen für ' . $event['titleAndDate']);

  echo html_open('div', ['class' => 'content bookingList']);

  echo html_open('div');
  renderEventSeatInfo($event);
  echo html_close('div');

  renderItemTable(getAdminBookings($eventId), getBookingFields(), getBookingListActions());

  echo html_close('div');
}


function renderBookingDetails($itemId)
{
  $item = null;
  $title = null;
  $creatingItem = $itemId == 'new';
  if ($creatingItem)
  {
    $eventId = get_param_value('eventId');
    $event = tryGetEventById($eventId, true, true);
    if ($event == null)
    {
      renderNotFoundError();
      return;
    }
    $title = 'Buchung für ' . $event['titleAndDate'];
  }
  else
  {
    $item = tryGetBookingById($itemId, true);
    if ($item == null)
    {
      renderNotFoundError();
      return;
    }
    $title = 'Buchung ' . $item['id'];
  }

  writeMainHtmlBeforeContent($title);

  echo html_open('div', ['class' => 'content bookingDetails']);

  if ($creatingItem)
  {
    echo html_open('div');
    renderEventSeatInfo($event);
    echo html_close('div');
    renderMainPageSaveBookingForm($event, true);
  }
  else
    renderItemDetails($creatingItem, $item, getBookingFields(), getBookingDetailsActions($item));

  echo html_close('div');
}


function getBookingFields()
{
  $fields = [];

  $field = newIdField();
  $fields[] = $field;

  $field = newForeignIdField('eventId', 'Veranstaltung', 'events');
  $field['visibleInList'] = false;
  $fields[] = $field;

  $field = newTextField('listOfPersons', 'Personen');
  $field['visibleInDetails'] = false;
  $fields[] = $field;

  $field = newTextField('listOfPersonsHtml', 'Personen');
  $field['visibleInList'] = false;
  $field['allowHtml'] = true;
  $fields[] = $field;

  if (getConfigValue('requestPhoneNumber'))
  {
    $field = newTextField('phoneNumber', 'Telefon');
    $field['visibleInList'] = false;
    $fields[] = $field;
  }

  if (getConfigValue('requestAddress'))
  {
    $field = newTextField('addressLine1', 'Straße und Hausnr.');
    $field['visibleInList'] = false;
    $fields[] = $field;

    $field = newTextField('addressLine2', 'PLZ und Ort');
    $field['visibleInList'] = false;
    $fields[] = $field;
  }

  $field = newTimestampField('insertTimestamp', 'Gebucht am');
  $fields[] = $field;

  $field = newClientIdField('insertClientId', 'Gebucht durch');
  $fields[] = $field;

  $field = newBooleanField('insertedAsAdmin', 'Als Admin gebucht');
  $field['visibleInList'] = false;
  $fields[] = $field;

  $field = newTimestampField('cancelTimestamp', 'Storniert am');
  $fields[] = $field;

  $field = newClientIdField('cancelClientId', 'Storniert durch');
  $fields[] = $field;

  return $fields;
}


function getBookingListActions()
{
  $actions = [];

  $action = newLinkAction('?p=bookings&itemId=new&eventId=' . get_param_value('eventId'), 'Buchung eintragen');
  $action['cssClass'] = 'saveButton';
  $action['visibleInDetails'] = false;
  $actions[] = $action;

  return $actions;
}


function getBookingDetailsActions($booking)
{
  $actions = [];

  if ($booking['cancelTimestamp'] == null)
  {
    $action = newAjaxPerItemAction('?a=cancelBooking', 'Buchung stornieren');
    $action['cssClass'] = 'deleteButton';
    $action['confirmText'] = 'Buchung stornieren?';
    $actions[] = $action;
  }

  return $actions;
}
