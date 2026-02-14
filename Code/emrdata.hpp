/**\
 * @file EMR_Data.hpp
 * @brief Declaration file for the Data Dictionary class, a concrete implementation
 * of the Dictionary container abstraction 
 * 
 * @author Kamiah Long
 * @note   Project - Dallas Clinic, Fall 2025
 * @note   IDE  : VSCode Server 3.9.3, Gnu Development Tools
 * @note   C++ Language Standard Version: C++17
 * @date   December 7, 2025
 */
#ifndef _EMR_DATA_HPP_
#define _EMR_DATA_HPP_
#include <iostream>
using namespace std;

// EMR_Data class declaration would go here
class Patient {
public:
    // Constructor, member functions, and data members would be defined here
    Patient();
    Patient(int id, const string& firstName, const string& lastName, int age, int phoneNumber, string address);
    void displayInfo() const;
    void sourceSheetName(const string& name);
    void dictSheetName(const string& name);

    //acessor methods
    int getPatientID() const;
    string getFirstName() const;
    string getLastName() const;
    int getAge() const;
    int getPhoneNumber() const;
    string getAddress() const;

    //mutator methods
    const int dictHeaders(const string& headers);
    const int SAMPLE_ROWS(const int rows);
    
private:
    int patientID;
    string firstName;
    string lastName;
    int age;
    int phoneNumber;
    string address;

};
#endif // _EMR_DATA_HPP_