DROP DATABASE IF EXISTS seatbooking;
CREATE DATABASE seatbooking;
USE seatbooking;

CREATE TABLE client (
  id INT NOT NULL AUTO_INCREMENT,
  token VARCHAR(255),
  persistent TINYINT,
  userName TEXT,
  deviceName TEXT,
  userGroup INT,
  remark TEXT,
  insertTimestamp TIMESTAMP NULL,
  editTimestamp TIMESTAMP NULL,
  editClientId INT,
  lastSeenTimestamp TIMESTAMP NULL,
  lastListOfPersons TEXT,
  lastPhoneNumber TEXT,
  lastAddressLine1 TEXT,
  lastAddressLine2 TEXT,
  ip VARCHAR(255),
  userAgent TEXT,
  hash VARCHAR(255),
  PRIMARY KEY (id),
  INDEX Index_lastSeenTimestamp(lastSeenTimestamp),
  INDEX Index_userGroup(userGroup),
  INDEX Index_hash(hash)
);

CREATE TABLE adminlog (
  id INT NOT NULL AUTO_INCREMENT,
  insertTimestamp TIMESTAMP NULL,
  clientId INT,
  itemType VARCHAR(255),
  itemId INT,
  action VARCHAR(255),
  newData TEXT,
  PRIMARY KEY (id),
  INDEX Index_clientId(clientId),
  INDEX Index_itemType(itemType),
  INDEX Index_itemId(itemId)
);

CREATE TABLE event (
  id INT NOT NULL AUTO_INCREMENT,
  startTimestamp TIMESTAMP NULL,
  title TEXT,
  notice TEXT,
  visitorLimit INT,
  capacity5Seats INT,
  capacity6Seats INT,
  releaseTimestamp TIMESTAMP NULL,
  bookingOpeningTimestamp TIMESTAMP NULL,
  bookingClosingTimestamp TIMESTAMP NULL,
  canceled TINYINT,
  isHighPriority TINYINT,
  remark TEXT,
  insertTimestamp TIMESTAMP NULL,
  editClientId INT,
  editTimestamp TIMESTAMP NULL,
  PRIMARY KEY (id),
  INDEX Index_releaseTimestamp(releaseTimestamp),
  INDEX Index_startTimestamp(startTimestamp)
);

CREATE TABLE booking (
  id INT NOT NULL AUTO_INCREMENT,
  eventId INT,
  listOfPersons TEXT,
  personCount INT,
  phoneNumber TEXT,
  addressLine1 TEXT,
  addressLine2 TEXT,
  insertedAsAdmin TINYINT,
  insertTimestamp TIMESTAMP NULL,
  insertClientId INT,
  cancelTimestamp TIMESTAMP NULL,
  cancelClientId INT,
  PRIMARY KEY (id),
  INDEX Index_eventId(eventId),
  INDEX Index_insertClientId(insertClientId),
  INDEX Index_cancelTimestamp(cancelTimestamp),
  INDEX Index_personCount(personCount)
);
