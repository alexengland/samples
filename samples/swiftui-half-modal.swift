
    import SwiftUI

    struct AddView: View {

        // Modal

        @Binding var isShown: Bool
        var modalHeight: CGFloat = 400

        // Calories input

        @State private var calories: Int?
        @FocusState var focusedField: Bool

        // Show keyboard or not based on modal state

        private func showKeyboard() {

            if (self.isShown == true) {

                self.focusedField = true

            } else {

                self.focusedField = false

            }

        }

        var body: some View {

            showKeyboard() // Toggle keyboard based on modal state

            return Group {

                ZStack {

                    // Background

                    Spacer()
                        .edgesIgnoringSafeArea(.all)
                        .frame(width: UIScreen.main.bounds.size.width, height: UIScreen.main.bounds.size.height)
                        .background(isShown ? Color.black.opacity( 0.5 ) : Color.clear)
                        .gesture(
                            TapGesture()
                                .onEnded { _ in
                                    withAnimation(.interpolatingSpring(stiffness: 300.0, damping: 30.0, initialVelocity: 10.0)) { self.isShown = false }
                                }
                        )

                    // Foreground

                    VStack {

                        Spacer()

                        ZStack {

                            Color.black.opacity(1.0)
                                .frame(width: UIScreen.main.bounds.size.width, height:modalHeight)
                                .cornerRadius(10)
                                .shadow(radius: 5)

                            TextField("0", value: $calories, format: .number)
                                .focused($focusedField)
                                .keyboardType(.numberPad)

                        }
                        .offset(y: isShown ? 0 : modalHeight)

                    }

                }.edgesIgnoringSafeArea(.all)

            }

        }

    }