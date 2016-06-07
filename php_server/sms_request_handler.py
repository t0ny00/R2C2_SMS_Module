import os.path
import time
import json
import subprocess
import request

request_path = "./route_request_sms"

if __name__ == "__main__":

	while (True):

		while not os.path.exists(request_path):
		    time.sleep(1)

		for sms_request in os.listdir(request_path):
			with open(os.path.join(request_path,sms_request)) as data_file:    
				data = json.load(data_file)
				origin = data["from"]
				destination = data["to"]
				response = json.loads(request.get("htttp://localhost:3002/api/recommendation/"+origin+"/"+destination)) 
				subprocess.call(["php","-f","send_sms.php",data["number"], response["bestRoute"]])
