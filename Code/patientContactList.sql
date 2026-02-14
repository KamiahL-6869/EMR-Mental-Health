-- Creates a patient contact list to collect basic information
-- This is a rough draft
-- Author: Kamiah Long
-- Date: December 7, 2025
CREATE TABLE PatientContactList (
    PatientID INTEGER PRIMARY KEY,     -- Unique identifier for each customer
    FirstName TEXT NOT NULL,            -- Customer's first name
    LastName TEXT NOT NULL,             -- Customer's last name
    Email TEXT NOT NULL,       		   -- Customer's email address
    PhoneNumber varchar(15),           -- Customer's phone number
    AddressLine1 varchar(255),         -- First line of the address
    AddressLine2 varchar(100),         -- Second line of the address (optional)
    City varchar(255),                 -- City of residence
    StateST varchar(255),              -- State of residence
    PostalCode varchar(10),            -- ZIP or postal code
    Country varchar(50) DEFAULT 'United States', -- Default country
    CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Record creation timestamp
    UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP, -- Last update timestamp
);
...
