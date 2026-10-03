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
driver.find_element(By.LINK_TEXT, "Services").click()
driver.find_element(By.LINK_TEXT, "Open Clubs").click()
driver.find_element(By.NAME, "name").send_keys("UIU Blood Bank")
driver.find_element(By.NAME, "university").send_keys("United International University")
Select(driver.find_element(By.NAME, "division")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "district")).select_by_value("Dhaka")
Select(driver.find_element(By.NAME, "upazila")).select_by_value("Badda")
driver.find_element(By.NAME, "reference").send_keys("Approved From Register")
driver.find_element(By.XPATH, "//button[contains(., 'Apply as Coordinator')]").click()