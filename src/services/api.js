import axios from "axios";

const api = axios.create({
  baseURL: "http://192.168.1.17:3001/bon-appetit-server/api/v1"
});

export default api;
