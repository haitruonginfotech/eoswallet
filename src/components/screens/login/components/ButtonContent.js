// @flow

import React from "react";
import { TouchableOpacity } from "react-native";
import { withNavigation } from "react-navigation";

import { ROUTE_NAMES } from "../../../../routes";
import { ContentContainer } from "./Common";

type Props = {
  navigation: Object,
  children: Object,
  color: string,
  onLogin: Function
};

const ButtonContent = ({
  navigation,
  children,
  color,
  onLogin
}: Props): Object => (
  /*<TouchableOpacity onPress={() => navigation.navigate(ROUTE_NAMES.MAIN_STACK)}>
    <ContentContainer color={color}>{children}</ContentContainer>
  </TouchableOpacity>*/
  <TouchableOpacity onPress={() => onLogin()}>
    <ContentContainer color={color}>{children}</ContentContainer>
  </TouchableOpacity>
);

export default withNavigation(ButtonContent);
