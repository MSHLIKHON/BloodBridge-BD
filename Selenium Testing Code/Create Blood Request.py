from selenium import webdriver
from selenium.webdriver.edge.service import Service
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.common.by import By
import os
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
import time
options = webdriver.ChromeOptions()
options.add_experimental_option("detach", True)
service_obj = Service()
driver = webdriver.Edge(options=options, service=service_obj)

driver.maximize_window()
driver.get("http://localhost/BloodBridge-BD/login.php")
driver.find_element(By.NAME, "email").send_keys("sadnan@gmail.com")
driver.find_element(By.XPATH, "//input[@type='password']").send_keys("Blood@123")
driver.find_element(By.XPATH, "//button[@type='submit']").click()
driver.find_element(By.LINK_TEXT, "New Request").click()

# 1. Blood Group: "O+"
Select(driver.find_element(By.NAME, "blood_group")).select_by_value("O+")

# 2. Units Needed: "2"
units_input = driver.find_element(By.NAME, "units")
units_input.clear()
units_input.send_keys("2")

# 3. Urgency: "Normal"
Select(driver.find_element(By.NAME, "urgency")).select_by_visible_text("Normal")

# 4. Blood Source: "Donor"
Select(driver.find_element(By.NAME, "source_type")).select_by_visible_text("Donor")

# 5. Location Division: "Dhaka", District: "Dhaka", Upazila: "Badda"
Select(driver.find_element(By.NAME, "division")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "district")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "upazila")).select_by_value("Badda")

# 6. Request For: "Self"
Select(driver.find_element(By.NAME, "patient_relation")).select_by_visible_text("Self")

# 7. Patient Last Received Blood: "2025-09-21" (HTML5 date inputs require YYYY-MM-DD format)
driver.find_element(By.NAME, "patient_last_received").send_keys("2025/09/21")


# 9. Consent Checkbox: Click
driver.find_element(By.NAME, "document_consent").click()

# 10. Click "Create & Notify"
driver.find_element(By.XPATH, "//button[@type='submit'][contains(., 'Create & Notify')]").click()