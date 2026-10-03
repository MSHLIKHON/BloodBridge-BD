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
driver.find_element(By.LINK_TEXT, "Create user account").click()
driver.find_element(By.NAME, "full_name").send_keys("Abdullah Al Toha")
driver.find_element(By.NAME, "email").send_keys("toha123@gmail.com")
driver.find_element(By.NAME, "phone").send_keys("01711149383")
Select(driver.find_element(By.NAME, "role")).select_by_value("seeker")
Select(driver.find_element(By.NAME, "division")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "district")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "upazila")).select_by_value("Badda")
driver.find_element(By.NAME, "password").send_keys("Abcd@1234")
driver.find_element(By.NAME, "password_confirmation").send_keys("Abcd@1234")
driver.find_element(By.XPATH, "//button[@type='submit'][contains(., 'Create & Verify Account')]").click()
# 1. Read the demo OTP dynamically from the page
otp_code = driver.find_element(By.XPATH, "//div[@class='otp-box']//strong").text.strip()

# 2. Type the OTP into the 6-digit code field
driver.find_element(By.NAME, "code").send_keys(otp_code)

# 3. Click "Verify Account"
driver.find_element(By.XPATH, "//button[@type='submit'][contains(., 'Verify Account')]").click()