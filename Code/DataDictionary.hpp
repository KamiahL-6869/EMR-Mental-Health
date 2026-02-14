/** @file DataDictionary.hpp
 * @brief Header file with definitions of concrte DataDictionary implementation
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
#ifndef _DATA_DICTIONARY_HPP_
#define _DATA_DICTIONARY_HPP_

#include <string>
#include <iostream>
using namespace std; 

/** @class Data Dictionary
 * @brief Concreate implementation of a data dictionary for EMR datasets
 * 
 * @note Scans a sheet called "EMR_Data" and produces a new 
 * sheet called "Data Dictionary" describing each field.
 */
template<class D>
class DataDictionary : public Dictionary<D> {
public:
    /** @brief Constructor
     * @param sourceSheetName Name of the source sheet with EMR dataset
     * @param dictSheetName Name of the output sheet for the data dictionary
     * @param sampleRows Number of rows to sample from the source sheet
     */
    DataDictionary(const string& sourceSheetName,
                   const string& dictSheetName,
                   size_t sampleRows); 
    protected:
    /** @brief Infer data type based on values
     * @param values Vector of values to analyze
     * @return Inferred data type as a string
     */
    string inferType(const vector<D>& values);  
    /** @brief Test if a string is a date
     * @param str String to test
     * @return True if the string is a valid date, false otherwise
     */
    bool isValidDate(const string& str);
}; 

#endif // _DATA_DICTIONARY_HPP_