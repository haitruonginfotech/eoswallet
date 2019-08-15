/**
 * Sample React Native App
 * https://github.com/facebook/react-native
 *
 * @format
 * @flow
 */

import React, { Component } from 'react';
import {
  StyleSheet,
  Text,
  View,
  TextInput,
  Button,
  TouchableHighlight,
  Image,
  Alert
} from 'react-native';

//import SignIn from "./src/SignIn";
import LogIn from "./src/LogIn";
import Dashboard from "./src/Dashboard";

export default class App extends Component {

  constructor(props) {
    super(props);
    this.state = {
      isLoggedIn: false
    }
  } 

  render() {
    if (this.state.isLoggedIn) 
      return <Dashboard onLogoutPress={() => this.setState({isLoggedIn: false})} />
    else
      return <LogIn onLoginPress={() => this.setState({isLoggedIn: true})} />
  }

}
