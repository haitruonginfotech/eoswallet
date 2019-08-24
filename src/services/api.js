import axios from "axios";

const api = axios.create({
  baseURL: "https://eoswallet.ai/api"
});

export default api;
