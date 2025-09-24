mosquitto_pub -h test.mosquitto.org -p 1883 -t "ESP32-DEV-001/sensors" -m '{
  "sensors": [
    {
      "type": "thermal",
      "value": "22.8 celsius"
    },
    {
      "type": "humidity",
      "value": "45.0 percent"
    },
    {
      "type": "light",
      "value": "26 percent"
    },
    {
      "type": "potentiometer",
      "value": "100 percent"
    },
    {
      "type": "geolocation",
      "subtype": "latitude",
      "value": "39.527685"
    },
    {
      "type": "geolocation",
      "subtype": "longitude",
      "value": "-107.696663"
    }
  ],
  "timestamp": 30091
}'
