// @flow

import React, { Component } from "react";
import { TouchableOpacity, Animated, View } from "react-native";

import Icon from "react-native-vector-icons/MaterialCommunityIcons";
import styled from "styled-components";

import ButtonContent from "./ButtonContent";
import { DefaultText } from "./Common";
import Input from "./Input";

import appStyles from "../../../../styles";
import api from "../../../../services/api";

const Container = styled(View)`
  width: 100%;
  height: 100%;
`;

const ButtonIcon = styled(Icon).attrs(({ iconName }) => ({
  name: iconName,
  size: 24
}))`
  color: ${({ theme }) => theme.colors.defaultWhite};
  margin-left: ${({ iconName }) => (iconName === "facebook" ? -4 : 0)}px;
`;

const SocialButtonWrapper = styled(View)`
  width: 100%;
  justify-content: space-between;
  align-items: center;
  flex-direction: row;
  padding-horizontal: ${({ theme }) => theme.metrics.largeSize}px;
`;

const SocialButtonsContainer = styled(View)`
  height: 50%;
  justify-content: flex-end;
`;

const ForgotPasswordContainer = styled(Animated.View)`
  width: 100%;
  justify-content: center;
  align-items: center;
`;

const ForgotPasswordWrapper = styled(View)`
  flex-direction: row;
`;

const RecoverTextButton = styled(TouchableOpacity)`
  margin-left: 4px;
`;

const createAnimationStyle = (animation: Object): Object => {
  const translateY = animation.interpolate({
    inputRange: [0, 1],
    outputRange: [-5, 0]
  });

  return {
    opacity: animation,
    transform: [
      {
        translateY
      }
    ]
  };
};

class LoginComponent extends Component {
  constructor(props) {
    super(props);
    this.state = {
      email: "",
      password: "",
      token: {
        email: true,
        password: true
      }
    };
    this.onLogin = this.onLogin.bind(this);
  }

  onLogin() {
    const { email, password, token } = this.state;
    const { navigation } = this.props;
    if (email == "" || password == "") {
      this.setState({
        token: {
          ...token,
          email: false,
          password: false
        }
      });
    } else {
    }
    console.log("this email is: " + email);
    alert("this email is: " + email);
  }

  onChangeInputEmail = text => {
    this.setState({ email: text });
  };

  onChangeInputPassword = text => {
    this.setState({ password: text });
  };

  _emailInputFieldAnimation = new Animated.Value(0);
  _passwordInputFieldAnimation = new Animated.Value(0);
  _loginButtonAnimation = new Animated.Value(0);
  _forgotPasswordTextAnimation = new Animated.Value(0);

  componentDidMount() {
    Animated.stagger(100, [
      Animated.timing(this._emailInputFieldAnimation, {
        toValue: 1,
        duration: 350
      }),
      Animated.timing(this._passwordInputFieldAnimation, {
        toValue: 1,
        duration: 350
      }),
      Animated.timing(this._loginButtonAnimation, {
        toValue: 1,
        duration: 350
      }),
      Animated.timing(this._forgotPasswordTextAnimation, {
        toValue: 1,
        duration: 350
      })
    ]).start();
  }

  renderInput = (
    placeholder: string,
    iconName: string,
    type: string,
    style: Object,
    name: string,
    value: String,
    onChangeInput: Function
  ): Object => (
    <Input
      placeholder={placeholder}
      iconName={iconName}
      style={style}
      type={type}
      name={name}
      value={value}
      onChangeText={onChangeInput}
    />
  );

  renderForgotPasswordText = (): Object => {
    const forgotPasswordTextAnimationStyle = createAnimationStyle(
      this._forgotPasswordTextAnimation
    );

    return (
      <ForgotPasswordContainer style={forgotPasswordTextAnimationStyle}>
        <ForgotPasswordWrapper>
          <DefaultText>Forgot your Password?</DefaultText>
          <RecoverTextButton>
            <DefaultText color={appStyles.colors.primaryColor}>
              Recover here
            </DefaultText>
          </RecoverTextButton>
        </ForgotPasswordWrapper>
      </ForgotPasswordContainer>
    );
  };

  renderSocialButton = (text: string, icon: string, color: string): Object => (
    <ButtonContent color={color}>
      <SocialButtonWrapper>
        <ButtonIcon iconName={icon} />
        <DefaultText>{text}</DefaultText>
        <View />
      </SocialButtonWrapper>
    </ButtonContent>
  );

  render() {
    const emailAnimationStyle = createAnimationStyle(
      this._emailInputFieldAnimation
    );
    const passwordAnimationStyle = createAnimationStyle(
      this._passwordInputFieldAnimation
    );
    const loginButtonAnimationStyle = createAnimationStyle(
      this._loginButtonAnimation
    );

    return (
      <Container>
        <Animated.View style={emailAnimationStyle}>
          {this.renderInput(
            "Email Address",
            "email-outline",
            "emailAddress",
            emailAnimationStyle,
            "email",
            this.state[name],
            this.onChangeInputEmail
          )}
        </Animated.View>
        <Animated.View style={passwordAnimationStyle}>
          {this.renderInput(
            "Password",
            "lock-outline",
            "password",
            passwordAnimationStyle,
            "password",
            this.state[name],
            this.onChangeInputPassword
          )}
        </Animated.View>
        <Animated.View style={loginButtonAnimationStyle}>
          <ButtonContent
            color={appStyles.colors.secondaryColor}
            onLogin={this.onLogin}
          >
            <DefaultText>LOGIN</DefaultText>
          </ButtonContent>
        </Animated.View>
        {this.renderForgotPasswordText()}
      </Container>
    );
  }
}

export default LoginComponent;
