<?php

function writeMainHtmlBeforeContent($pageTitle = null)
{
  echo '<!DOCTYPE html>';
  echo html_node('meta', null, ['charset' => 'utf-8']);
  echo html_node('meta', null, ['name' => 'viewport', 'content' => 'width=device-width, initial-scale=1']);

  $title = getConfigValue('instanceTitle', 'instanceTitle');
  if ($pageTitle != null)
    $title = $pageTitle . ' – ' . $title;
  echo html_node('title', $title);

  $faviconPath = getConfigValue('faviconPath');
  if ($faviconPath != null)
    echo html_node('link', null, ['rel' => 'icon', 'href' => $faviconPath]);

  write_css_include_tag('public/stylesheet.css');
  write_script_include_tag('public/common.js');

  # header
  echo html_open('div', ['class' => 'headerOuter']);
  echo html_open('div', ['class' => 'header']);
  $classes = 'title';
  if (getConfigValue('isProductionInstance') !== true)
    $classes .= ' nonProdInstance';
  $titleSpan = html_node('span', getConfigValue('instanceHeadline', 'instanceHeadline'), ['class' => $classes]);
  echo html_a('.', $titleSpan, ['title' => 'Zur Startseite']);
  echo getConfigValue('textAfterTitle');
  echo html_node('span', 'Live', ['id' => 'autoReloadIndicator']);
  echo html_close('div');
  echo html_close('div');

  renderUnsupportedBrowserWarning();

  # navBar
  echo html_open('div', ['class' => 'navBar']);
  if (isClientAdmin() || $pageTitle != null)
    echo html_a('.', 'Startseite');
  if (isClientAdmin())
    echo html_a('?p=admin', 'Administration');
  echo html_close('div');

  # pageTitle
  if ($pageTitle != null)
  {
    echo html_open('div', ['class' => 'pageTitleOuter']);
    echo html_node('div', $pageTitle, ['class' => 'pageTitle']);
    echo html_close('div');
  }
}


function renderUnsupportedBrowserWarning()
{
  if (user_agent_is_not_supported())
  {
    echo html_open('div', ['class' => 'content']);
    echo html_open('div');
    echo html_node('div', 'Achtung: Der verwendete Webbrowser ist veraltet und wird nicht mehr unterstützt.<br>Zu weiteren Informationen siehe <a href="https://www.browser-update.org/de/update.html" target="_blank">www.browser-update.org</a>.', ['class' => 'textBlock errorBox']);
    echo html_close('div');
    echo html_close('div');
  }
}


function renderForbiddenError()
{
  header('HTTP/1.1 403 Forbidden');
  writeMainHtmlBeforeContent();
  renderPageErrorBox('Dieses Gerät hat keine Berechtigung für die angeforderte Seite.');
}


function renderNotFoundError()
{
  header('HTTP/1.1 404 Not Found');
  writeMainHtmlBeforeContent();
  renderPageErrorBox('Die angeforderte Seite existiert nicht.');
}


function writeMainHtmlAfterContent()
{
  $footerText = getConfigValue('footerText');
  if ($footerText != null)
    echo html_node('footer', $footerText);
}


function renderPageErrorBox($msg)
{
  echo html_open('div', ['class' => 'content']);
  echo html_open('div');
  echo html_node('div', 'Fehler:<br>' . $msg, ['class' => 'textBlock errorBox']);
  echo html_close('div');
  echo html_close('div');
}


function renderMainPage()
{
  $events = getMainPageEvents(true, true);
  $currentBookingByEvent = getCurrentBookingByEventForClient();

  writeMainHtmlBeforeContent();

  echo html_open('div', ['class' => 'content mainPage']);

  renderMainPageNotice();

  if (count($events) == 0)
  {
    echo html_open('div');
    echo html_open('div', ['class' => 'textBlock']);
    echo 'Aktuell stehen keine Veranstaltungen zur Buchung bereit.';
    echo html_close('div');
    echo html_close('div');
  }
  else
  {
    foreach ($events as $event)
    {
      renderMainPageEvent($event, array_value($currentBookingByEvent, $event['id']));
    }
  }

  echo html_close('div');

  # Auto reload
  echo html_input('hidden', null, calculateAutoReloadHash($events), ['id' => 'autoReloadHash']);
  echo html_node('script', 'enableAutoReload()');
}


function calculateAutoReloadHash($events)
{
  $items = [];
  $items[] = getAppVersion();
  foreach ($events as $event)
  {
    $items[] = $event['id'];
    $items[] = $event['editTimestamp'];
    $items[] = $event['visitorCount'];
    if (time() > $event['startTimestamp'])
      $items[] = 1;
    else if (time() > $event['bookingClosingTimestamp'])
      $items[] = 2;
    else if (time() > $event['bookingOpeningTimestamp'])
      $items[] = 3;
    else
      $items[] = 4;
  }
  return substr(md5(implode('.', $items)), 0, 8);
}


function handleAutoReloadCheckAction()
{
  $events = getMainPageEvents(true);
  if (get_param_value('autoReloadHash') != calculateAutoReloadHash($events))
    echo 'location.reload();';
}


function renderMainPageNotice()
{
  echo html_open('div');
  echo html_open('div', ['class' => 'textBlock framedBox']);
  echo html_node('span', 'Hinweise', ['class' => 'framedBoxTitle']);
  echo getConfigValue('mainPageText');
  echo html_close('div');
  echo html_close('div');
}


function renderMainPageEvent($event, $booking)
{
  $boxTitle = '';
  $divClass = '';
  if ($event['canceled'] == 1)
    $divClass = 'canceled';
  else if (time() < $event['bookingOpeningTimestamp'])
    $divClass = 'preview';
  else if (time() < $event['bookingClosingTimestamp'])
    $divClass = 'bookingOpen';
  else
    $divClass = 'bookingClosed';

  echo html_open('div');
  echo html_open('div', ['class' => 'event ' . $divClass]);

  $persons = null;
  $bookingCanceled = false;
  if ($booking != null)
  {
    $persons = explode(';', $booking['listOfPersons']);
    $bookingCanceled = $booking['cancelTimestamp'] != null;
  }
  $hasActiveBooking = $persons != null && !$bookingCanceled;

  renderMainPageEventBasicInfo($event, $hasActiveBooking);

  if ($event['canceled'] != 1)
  {
    if (time() >= $event['bookingOpeningTimestamp'])
      renderEventSeatInfo($event, $hasActiveBooking);

    if ($persons != null)
      renderMainPageBookingStatus($persons, $bookingCanceled);

    if (isBookingOpen($event))
    {
      if ($hasActiveBooking)
        renderMainPageCancelBookingForm($event);
      else if (!$event['hasVisitorLimit'] || $event['freeSeatCount'] > 0)
        renderMainPageSaveBookingForm($event, false, $persons, $bookingCanceled);
    }
  }

  echo html_close('div');
  echo html_close('div');
}


function renderMainPageEventBasicInfo($event, $hasActiveBooking)
{
  # title and date
  echo html_open('div', ['class' => 'textBlock titleAndDate']);
  echo $event['title'];
  echo ' am ';
  echo formatTimestampLocalLong($event['startTimestamp'], 'minute', false);
  echo html_close('div');

  echo html_open('div', ['class' => 'textBlock']);

  if ($event['canceled'] == 1)
    echo html_node('div', 'Veranstaltung ist abgesagt');

  # notice
  if ($event['notice'] != null)
    echo html_node('div', $event['notice'], ['class' => 'notice preWrapped']);

  # booking period
  if ($event['canceled'] != 1)
  {
    echo html_open('div', ['class' => 'bookingPeriod']);
    if (time() < $event['bookingOpeningTimestamp'])
    {
      echo 'Buchung: ';
      echo formatTimestampLocalLong($event['bookingOpeningTimestamp'], 'minute', false);
      echo ' – ';
      echo formatTimestampLocalLong($event['bookingClosingTimestamp'], 'minute', false);
    }
    else if (time() < $event['bookingClosingTimestamp'])
    {
      if ($hasActiveBooking)
        echo 'Stornierung bis ';
      else
        echo 'Buchung bis ';
      echo formatTimestampLocalLong($event['bookingClosingTimestamp'], 'minute', false);
    }
    else
    {
      echo 'Buchung beendet';
    }
    echo html_close('div');
  }

  echo html_close('div');
}


function renderEventSeatInfo($event, $hasActiveBooking = false)
{
  echo html_open('div', ['class' => 'seatsInfo']);

  $visitorCount = $event['visitorCount'];
  $freeSeatCount = $event['freeSeatCount'];
  $maxVisitorCount = $visitorCount + max($freeSeatCount, 0);
  $isBookingOpen = time() < $event['bookingClosingTimestamp'];

  $showVisitors = $visitorCount > 0 && (!$event['hasVisitorLimit'] || $freeSeatCount > 0 || getConfigValue('showVisitorsWhenFullyBooked', true));
  if ($showVisitors)
  {
    echo $visitorCount;
    echo ' Teilnehmer';
  }

  if ($event['hasVisitorLimit'] && ($isBookingOpen || getConfigValue('showFreeSeatsAfterBookingClosing', true)))
  {
    if ($showVisitors)
    {
      echo ', ';
    }
    if ($freeSeatCount > 0)
    {
      echo html_open('span', ['class' => 'freeSeats']);
      echo $freeSeatCount;
      if ($freeSeatCount == 1)
        echo ' freier Platz';
      else
        echo ' freie Plätze';
      echo html_close('span');
    }
    else
    {
      $class = '';
      if (!$hasActiveBooking && $isBookingOpen)
        $class = 'noFreeSeatsRed';
      echo html_node('span', 'ausgebucht', ['class' => $class]);
    }

    # bar
    $barWidth = 100;
    $usedWidth = round($visitorCount / $maxVisitorCount * $barWidth);
    $tooltip = round($visitorCount / $maxVisitorCount * 100) . ' % belegt';
    echo html_open('div',  ['class' => 'capacityBar', 'title' => $tooltip]);
    echo html_open('div',  ['style' => 'width: ' . $barWidth . 'px']);
    echo html_node('div', '', ['style' => 'width: ' . $usedWidth . 'px']);
    echo html_close('div');
    echo html_close('div');
  }

  echo html_close('div');
}


function renderMainPageBookingStatus($persons, $bookingCanceled)
{
  echo html_open('div', ['class' => 'textBlock bookingStatus']);
  echo html_open('div');
  echo html_node('span', 'Meine Buchung:', ['class' => 'myBookingTitle']);

  if ($bookingCanceled)
    echo html_node('div', ' storniert', ['class' => 'redText']);
  else
  {
    echo html_open('div');
    if (count($persons) > 1)
      echo html_open('ol');
    foreach ($persons as $personStr)
    {
      if (count($persons) > 1)
        echo html_open('li');
      $person = explode(',', $personStr);
      $surname = array_value($person, 0);
      $lastname = array_value($person, 1);

      echo html_encode($surname);
      echo ' ';
      echo html_encode($lastname);
      if (count($persons) > 1)
        echo html_close('li');
    }
    if (count($persons) > 1)
      echo html_close('ol');
    echo html_close('div');

    $showFormScript = "event.target.parentNode.parentNode.parentNode.parentNode.classList.add('cancelBookingFormOpened');disableAutoReload();";
    $button = html_button('Stornieren', ['class' => 'linkButton', 'onclick' => $showFormScript]);
    echo html_node('div', $button, ['class' => 'cancelBookingFormPlaceholder']);
  }

  echo html_close('div');
  echo html_close('div');
}


function renderMainPageCancelBookingForm($event)
{
  $hideFormScript = "event.target.parentNode.parentNode.parentNode.classList.remove('cancelBookingFormOpened');enableAutoReload();";

  echo html_open('div', ['class' => 'cancelBookingForm']);
  echo html_open('form', ['action' => '?a=cancelBooking', 'onsubmit' => 'postForm(event)']);
  echo html_input('hidden', 'eventId', $event['id']);
  echo html_form_submit_button('Stornierung bestätigen', ['class' => 'deleteButton']);
  echo html_button('Abbrechen', ['class' => 'linkButton', 'onclick' => $hideFormScript]);
  writeFormToken();
  echo html_close('form');

  echo html_close('div');
}


function renderMainPageSaveBookingForm($event, $asAdmin, $persons = null, $bookingCanceled = false)
{
  $showFormScript = "event.target.parentNode.parentNode.classList.add('saveBookingFormOpened'); focusFirstChildInputNode(event.target.parentNode.parentNode);disableAutoReload();";
  $hideFormScript = "event.target.parentNode.parentNode.parentNode.classList.remove('saveBookingFormOpened');enableAutoReload();";

  if (!$asAdmin)
  {
    if ($bookingCanceled)
      $buttonText = 'Erneut buchen';
    else
      $buttonText = 'Teilnehmen';
    $button = html_button($buttonText, ['class' => 'saveButton', 'onclick' => $showFormScript]);
    echo html_node('div', $button, ['class' => 'saveBookingFormPlaceholder']);
  }

  echo html_open('div', ['class' => 'saveBookingForm']);
  if ($asAdmin)
    echo html_node('div', 'Teilnehmer aus einem Haushalt:', ['class' => 'formTitle']);
  else
    echo html_node('div', 'Teilnehmer aus meinem Haushalt:', ['class' => 'formTitle']);
  echo html_open('form', ['action' => '?a=saveBooking', 'onsubmit' => 'postForm(event)']);

  if (!$asAdmin && $persons == null)
  {
    $persons = explode(';', getClientValue('lastListOfPersons'));
  }
  $phoneNumber = getClientValue('lastPhoneNumber');
  $addressLine1 = getClientValue('lastAddressLine1');
  $addressLine2 = getClientValue('lastAddressLine2');

  for ($i = 0; $i < 6; $i++)
  {
    echo html_open('div');

    $person = explode(',', array_value($persons, $i));
    $surname = clean_whitespaces(array_value($person, 0));
    $lastname = clean_whitespaces(array_value($person, 1));

    echo html_input('text', 'surname' . ($i + 1), $surname, ['placeholder' => 'Vorname', 'autocomplete' => 'chrome-off']);
    echo html_input('text', 'lastname' . ($i + 1), $lastname, ['placeholder' => 'Nachname', 'autocomplete' => 'chrome-off']);

    echo html_close('div');
  }

  if (getConfigValue('requestPhoneNumber'))
  {
    echo html_open('div');
    echo html_node('div', 'Telefon (erf.) ', ['class' => 'phoneNumberLabel']);
    echo html_input('tel', 'phoneNumber', $phoneNumber, ['placeholder' => 'Telefonnummer']);
    echo html_close('div');
  }

  if (getConfigValue('requestAddress'))
  {
    echo html_open('div');
    echo html_node('div', 'Straße und Hausnr. (erf.) ', ['class' => 'phoneNumberLabel']);
    echo html_input('text', 'addressLine1', $addressLine1, ['placeholder' => 'Straße und Hausnr.']);
    echo html_close('div');

    echo html_open('div');
    echo html_node('div', 'PLZ und Ort (erf.) ', ['class' => 'phoneNumberLabel']);
    echo html_input('text', 'addressLine2', $addressLine2, ['placeholder' => 'PLZ und Ort']);
    echo html_close('div');
  }

  if (getClientValue('persistent') != 1)
    echo html_node('div', 'Hinweis: Es wird ein Browser-Cookie gespeichert. <br>Eine Stornierung ist vom selben Gerät möglich.', ['class' => 'cookieInfo']);

  echo html_form_submit_button('Buchung speichern', ['class' => 'saveButton']);
  if (!$asAdmin)
    echo html_button('Abbrechen', ['class' => 'linkButton', 'onclick' => $hideFormScript]);

  echo html_input('hidden', 'eventId', $event['id']);
  if ($asAdmin)
  {
    echo html_input('hidden', 'asAdmin', 1);
    echo html_node('script', 'focusFirstChildInputNode(document.body);');
  }
  writeFormToken();
  echo html_close('form');
  echo html_close('div');
}


function renderHelpPage()
{
  writeMainHtmlBeforeContent('Hilfe');

  echo html_open('div', ['class' => 'content helpPage']);
  echo html_open('div', ['class' => 'textBlock']);

  echo getConfigValue('helpPageText');

  echo html_open('div', ['class' => 'debugInfo']);
  echo 'Gerätenummer: ';
  echo getClientValue('id');
  echo ' (';
  if (getClientValue('persistent') == 1)
    echo 'dauerhaft';
  else
  {
    echo html_open('form', ['action' => '?a=makeClientPersistent', 'onsubmit' => 'postForm(event)']);
    echo html_form_submit_button('Cookie dauerhaft speichern', ['class' => 'inlineLinkButton']);
    writeFormToken();
    echo html_close('form');
  }
  echo ')';

  echo '<br>';
  echo 'Softwareversion: ';
  echo getAppVersion();
  echo html_close('div');

  echo html_close('div');
  echo html_close('div');
}


function renderAdminPage()
{
  if (!isClientAdmin())
  {
    renderForbiddenError();
    return;
  }
  writeMainHtmlBeforeContent('Administration');

  echo html_open('div', ['class' => 'content adminPage']);

  echo html_open('ul');
  echo html_node('li', html_a('?p=visitorList', 'Anwesenheitslisten'));
  echo html_node('li', html_a('?p=events', 'Veranstaltungen verwalten'));
  echo html_node('li', html_a('?p=bookings', 'Buchungen verwalten'));
  echo html_node('li', html_a('?p=clients', 'Geräte verwalten'));
  echo html_node('li', html_a('?p=adminlog', 'Admin-Protokoll'));
  echo html_node('li', html_a('?p=bookingSimulator', 'Buchungs-Simulator'));
  echo html_close('ul');

  echo html_close('div');
}


function renderBookingSimulator()
{
  writeMainHtmlBeforeContent('Buchungs-Simulator');
  echo html_open('div', ['class' => 'content bookingSimulator']);

  echo html_open('form', ['action' => '?a=bookingSimulator', 'onsubmit' => 'postForm(event)']);

  echo html_open('div');
  echo html_node('span', 'Anzahl 5er-Stuhlreihen');
  echo html_input('number', 'capacity5Seats', 5);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 6er-Stuhlreihen');
  echo html_input('number', 'capacity6Seats', 5);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Besucher-Limit');
  echo html_input('number', 'visitorLimit', 50);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 6er-Gruppen');
  echo html_input('number', 'groupCount6Persons', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 5er-Gruppen');
  echo html_input('number', 'groupCount5Persons', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 4er-Gruppen');
  echo html_input('number', 'groupCount4Persons', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 3er-Gruppen');
  echo html_input('number', 'groupCount3Persons', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl 2er-Gruppen');
  echo html_input('number', 'groupCount2Persons', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl Einzelpersonen');
  echo html_input('number', 'groupCount1Person', 2);
  echo html_close('div');

  echo html_open('div');
  echo html_node('span', 'Anzahl Personen neue Buchung');
  echo html_input('number', 'personCountNewBooking', 0);
  echo html_close('div');

  echo html_open('div');
  echo html_form_submit_button('Berechnen', ['class' => 'saveButton']);
  echo html_close('div');

  writeFormToken();
  echo html_close('form');

  echo html_node('div', '', ['id' => 'simulatorResult', 'class' => 'textBlock', 'style' => 'font-size: 90%']);

  echo html_close('div');
}


function handleBookingSimulatorAction()
{
  $event = [];
  $event['capacity5Seats'] = intval(get_param_value('capacity5Seats'));
  $event['capacity6Seats'] = intval(get_param_value('capacity6Seats'));
  $event['visitorLimit'] = intval(get_param_value('visitorLimit'));

  $rows = [];
  $rows[] = ['personCount' => 6, 'count' => intval(get_param_value('groupCount6Persons'))];
  $rows[] = ['personCount' => 5, 'count' => intval(get_param_value('groupCount5Persons'))];
  $rows[] = ['personCount' => 4, 'count' => intval(get_param_value('groupCount4Persons'))];
  $rows[] = ['personCount' => 3, 'count' => intval(get_param_value('groupCount3Persons'))];
  $rows[] = ['personCount' => 2, 'count' => intval(get_param_value('groupCount2Persons'))];
  $rows[] = ['personCount' => 1, 'count' => intval(get_param_value('groupCount1Person'))];

  $additionalPersonCount = intval(get_param_value('personCountNewBooking'));
  if ($additionalPersonCount != null)
    $rows[] = ['personCount' => $additionalPersonCount, 'count' => 1];

  echo 'byId("simulatorResult").innerHTML = "';
  $freeSeats = calculateFreeSeatsInner($event, $rows, true);
  echo '<br>freie Sitzplätze: ';
  echo $freeSeats;
  echo '"';
}


function renderDebugFreeSeatsCalculation()
{
  if (!isClientAdmin())
  {
    renderForbiddenError();
    return;
  }

  $eventId = get_param_value('itemId');
  $event = tryGetEventById($eventId);
  if ($event == null)
  {
    renderNotFoundError();
    return;
  }

  writeMainHtmlBeforeContent('Untersuchung der Freie-Sitze-Berechung');

  echo html_open('div', ['class' => 'content']);
  echo html_open('div', ['class' => 'textBlock']);

  $freeSeats = calculateFreeSeatCount($event, null, true);
  echo '<br>freeSeats: ';
  echo $freeSeats;

  echo html_close('div');
  echo html_close('div');
}
