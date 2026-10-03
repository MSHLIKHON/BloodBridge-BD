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
import time
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys

driver.maximize_window()
driver.get("https://eticket.railway.gov.bd/")
import time
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
time.sleep(2)
# 1. Click "I Agree" on the initial consent popup modal
driver.find_element(By.XPATH, "//button[contains(translate(text(), 'AGREE', 'agree'), 'agree')]").click()

# 2. From Station: "Dhaka"
from_station = driver.find_element(By.XPATH, "//input[@formcontrolname='fromCity' or @name='fromcity' or contains(@placeholder, 'From')]")
from_station.click()
from_station.send_keys(Keys.CONTROL + "a", Keys.BACKSPACE)
from_station.send_keys("Dhaka")
time.sleep(1)
from_station.send_keys(Keys.ARROW_DOWN, Keys.ENTER)

# 3. To Station: "Chattogram"
to_station = driver.find_element(By.XPATH, "//input[@formcontrolname='toCity' or @name='tocity' or contains(@placeholder, 'To')]")
to_station.click()
to_station.send_keys(Keys.CONTROL + "a", Keys.BACKSPACE)
to_station.send_keys("Chattogram")
time.sleep(1)
to_station.send_keys(Keys.ARROW_DOWN, Keys.ENTER)# 4. Date of Journey: "13-Oct-2026"
date_input = driver.find_element(By.XPATH, "//input[@formcontrolname='doj' or @name='doj' or contains(@placeholder, 'Pick a date')]")
date_input.send_keys(Keys.CONTROL + "a", Keys.BACKSPACE)
date_input.send_keys("13-Oct-2026", Keys.ENTER)

Select(driver.find_element(By.ID, "choose_class")).select_by_value("S_CHAIR")
driver.find_element(By.XPATH, "//*[contains(text(), 'S_CHAIR') or contains(text(), 'SHOVAN CHAIR')]").click()

# 6. Click "Search Train" / "Find Ticket"
driver.find_element(By.XPATH, "//button[@type='submit' or contains(., 'Search Train') or contains(., 'FIND TICKET')]").click()