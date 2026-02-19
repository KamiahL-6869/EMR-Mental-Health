/** @file DataDictionary.cpp
 * @brief Implementation file for concrete DataDictionary implementation
 *   of the Dictionary container abstraction
 *
 * @author Kamiah Long
 * @note   Project - Dallas Clinic, Fall 2025
 * @note   IDE  : VSCode Server 3.9.3, Gnu Development Tools
 * @note   C++ Language Standard Version: C++17
 * @date   December 7, 2025
 *
 * Declares a Data Dictionary.
 */
/*******************************************************
 * Generate a Data Dictionary for an EMR Dataset
 * Author: Kamiah L.
 * 
 * Scans a sheet called "EMR_Data" and produces a new 
 * sheet called "Data Dictionary" describing each field.
 *******************************************************/
#ifndef _DATA_DICTIONARY_CPP_
#define _DATA_DICTIONARY_CPP_

#include <string>
#include <iostream>
#include "DataDictionary.hpp"
using namespace std; 

void createDataDictionary() {
  const sourceSheetName = "EMR_Data";     // Sheet with EMR dataset
  const dictSheetName = "Data Dictionary";// Output sheet name
  const SAMPLE_ROWS = 10;                 // Number of rows to sample
  
  const ss = SpreadsheetApp.getActive();
  const source = ss.getSheetByName(sourceSheetName);
  if (!source) throw new Error("Source sheet not found: " + sourceSheetName);

Logger.log("Looking for sheet: " + sourceSheetName);
Logger.log("Sheets in this file: " + 
  SpreadsheetApp.getActive().getSheets().map(s => s.getName()));
  

  // Get data
  const data = source.getDataRange().getValues();
  if (data.length < 2) throw new Error("Data sheet has no rows.");

  const headers = data[0];
  const rows = data.slice(1, 1 + SAMPLE_ROWS);

  // Create or clear dictionary sheet
  let dict = ss.getSheetByName(dictSheetName);
  if (!dict) dict = ss.insertSheet(dictSheetName);
  else dict.clear();

  // Set header row
  const dictHeaders = [
    "Field Name",
    "Data Type",
    "Description",
    "Example Value",
    "Missing Count",
    "Unique Values (sample)"
  ];
  dict.appendRow(dictHeaders);

  // Analyze each column
  headers.forEach((header, col) => {
    const columnValues = rows.map(r => r[col]);
    const nonEmpty = columnValues.filter(v => v !== "" && v !== null);

    const dataType = inferType(nonEmpty);
    const example = nonEmpty.length ? nonEmpty[0] : "";
    const missingCount = columnValues.length - nonEmpty.length;

    const uniqueValues = [...new Set(nonEmpty)]
      .slice(0, 10)
      .join(", ");

    dict.appendRow([
      header,
      dataType,
      "",                         // placeholder for human descriptions
      example,
      missingCount,
      uniqueValues
    ]);
  });

  dict.autoResizeColumns(1, dictHeaders.length);
}


/*******************************************************
 * Infer data type based on values
 *******************************************************/
void inferType(const Dictionary<D>& values) {
  if (values.empty()) return;

  bool isNum = true, isDate = true, isBool = true;

  for (const auto& v : values) {
    const auto t = typeid(v).name();

    if (t == "string") {
      // Test date
      if (!isValidDate(v)) isDate = false;

      // Test boolean
      if (!["true","false","yes","no"].includes(v.toLowerCase()))
        isBool = false;

      // Test number
      if (isNaN(parseFloat(v))) isNum = false;
    } else if (t === "number") {
      // Numbers cannot be boolean or text-dates
      isBool = false;
    } else if (Object.prototype.toString.call(v) === "[object Date]") {
      // True date object
      isNum = false;
      isBool = false;
    }
  });

  if (isDate) return "Date";
  if (isNum) return "Numeric";
  if (isBool) return "Boolean";
  return "String";
};


/*******************************************************
 * Test if a string is a date
 *******************************************************/
function isValidDate(str) {
  const d = new Date(str);
  return !isNaN(d.getTime());
}

#endif // _DATA_DICTIONARY_CPP_