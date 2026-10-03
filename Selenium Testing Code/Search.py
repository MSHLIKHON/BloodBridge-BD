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
driver.find_element(By.LINK_TEXT, "Search").click()
Select(driver.find_element(By.NAME, "blood_group")).select_by_value("O+")
Select(driver.find_element(By.NAME, "division")).select_by_value("Barishal")
Select(driver.find_element(By.NAME, "district")).select_by_value("Barishal")
Select(driver.find_element(By.NAME, "upazila")).select_by_value("Barisal Sadar")
driver.find_element(By.XPATH, "//button[@type='submit'][contains(., 'Search Blood')]").click()
